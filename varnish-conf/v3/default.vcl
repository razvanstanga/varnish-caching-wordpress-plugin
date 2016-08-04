include "conf/backend.vcl";
include "conf/acl.vcl";

import std;

include "lib/xforward.vcl";
include "lib/cloudflare.vcl";
include "lib/purge.vcl";
include "lib/bigfiles.vcl";
include "lib/static.vcl";

# Pick just one of the following:
# (or don't use either of these if your application is "adaptive")
# include "lib/mobile_cache.vcl";
# include "lib/mobile_pass.vcl";

### WordPress-specific config ###
sub vcl_recv {
    # pipe on weird http methods
    if (req.request !~ "^GET|HEAD|PUT|POST|TRACE|OPTIONS|DELETE$") {
        return(pipe);
    }

    # redirect yourdomain.com to www.yourdomain.com
    #if (req.http.host ~ "^yourdomain\.com$") {
    #    error 750 "http://www.yourdomain.com" + req.url;
    #}

    # if you use a subdomain for admin section, do not cache it
    #if (req.http.host ~ "admin.yourdomain.com") {
    #    set req.http.X-VC-Cacheable = "NO:Admin domain";
    #    return(pass);
    #}

    ### Check for reasons to bypass the cache!
    # never cache anything except GET/HEAD
    if (req.request != "GET" && req.request != "HEAD") {
        set req.http.X-VC-Cacheable = "NO:Request method:" + req.request;
        return(pass);
    }

    # don't cache logged-in users. you can set users `logged in cookie` name in settings
    if (req.http.Cookie ~ "flxn34napje9kwbwr4bjwz5miiv9dhgj87dct4ep0x3arr7ldif73ovpxcgm88vs") {
        set req.http.X-VC-Cacheable = "NO:Found logged in cookie";
        return(pass);
    }

    # don't cache ajax requests
    if (req.http.X-Requested-With == "XMLHttpRequest") {
        set req.http.X-VC-Cacheable = "NO:Requested with: XMLHttpRequest";
        return(pass);
    }

    # don't cache these special pages. Not needed, left here as example
    #if (req.url ~ "nocache|wp-admin|wp-(comments-post|login|activate|mail)\.php|bb-admin|server-status|control\.php|bb-login\.php|bb-reset-password\.php|register\.php") {
    #    set req.http.X-VC-Cacheable = "NO:Special page: " + req.url;
    #    return(pass);
    #}

    ### looks like we might actually cache it!
    # fix up the request
    set req.grace = 2m;
    set req.url = regsub(req.url, "\?replytocom=.*$", "");

    # strip query parameters from all urls (so they cache as a single object)
    # be carefull using this option
    #if (req.url ~ "\?.*") {
    #    set req.url = regsub(req.url, "\?.*", "");
    #}

    # Remove has_js, Google Analytics __*, and wooTracker cookies.
    set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(__[a-z]+|has_js|wooTracker)=[^;]*", "");
    set req.http.Cookie = regsub(req.http.Cookie, "^;\s*", "");
    if (req.http.Cookie ~ "^\s*$") {
        unset req.http.Cookie;
    }

    return(lookup);
}

sub vcl_hash {
    set req.http.hash = req.url;
    if (req.http.host) {
        set req.http.hash = req.http.hash + "#" + req.http.host;
    } else {
        set req.http.hash = req.http.hash + "#" + server.ip;
    }
    # Add the browser cookie only if a WordPress cookie found. Not needed, left here as example
    #if (req.http.Cookie ~ "wp-postpass_|wordpress_logged_in_|comment_author|PHPSESSID") {
    #    hash_data(req.http.Cookie);
    #    set req.http.hash = req.http.hash + "#" + req.http.Cookie;
    #}
}

sub vcl_fetch {
    # make sure grace is at least 2 minutes
    if (beresp.grace < 2m) {
        set beresp.grace = 2m;
    }

    # overwrite ttl with X-VC-TTL
    if (beresp.http.X-VC-TTL) {
        set beresp.ttl = std.duration(beresp.http.X-VC-TTL + "s", 0s);
    }

    # catch obvious reasons we can't cache
    if (beresp.http.Set-Cookie) {
        set beresp.ttl = 0s;
    }

    # Don't cache object as instructed by header bereq.X-VC-Cacheable
    if (req.http.X-VC-Cacheable ~ "^NO") {
        set beresp.http.X-VC-Cacheable = req.http.X-VC-Cacheable;
        return(hit_for_pass);

    # Varnish determined the object was not cacheable
    } else if (beresp.ttl <= 0s) {
        if (!beresp.http.X-VC-Cacheable) {
            set beresp.http.X-VC-Cacheable = "NO:Not cacheable, ttl: "+ beresp.ttl;
        }
        return(hit_for_pass);

    # You are respecting the Cache-Control=private header from the backend
    } else if (beresp.http.Cache-Control ~ "private") {
        set beresp.http.X-VC-Cacheable = "NO:Cache-Control=private";
        return(hit_for_pass);

    # Cache object
    } else if (beresp.http.X-VC-Enabled ~ "true") {
        if (!beresp.http.X-VC-Cacheable) {
            set beresp.http.X-VC-Cacheable = "YES:Is cacheable, ttl: " + beresp.ttl;
        }

    # Do not cache object
    } else if (beresp.http.X-VC-Enabled ~ "false") {
        if (!beresp.http.X-VC-Cacheable) {
            set beresp.http.X-VC-Cacheable = "NO:Disabled";
        }
        set beresp.ttl = 0s;
    }

    # Avoid caching error responses
    if (beresp.status == 404 || beresp.status >= 500) {
        set beresp.ttl   = 0s;
        set beresp.grace = 15s;
    }

    # Deliver the content
    return(deliver);
}

sub vcl_error {
    if (obj.status == 750) {
        set obj.http.Location = obj.response;
        set obj.status = 302;
        return(deliver);
    }
}
