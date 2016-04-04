# static.vcl -- Static File Caching for Varnish

sub vcl_recv {
    if (req.method ~ "^(GET|HEAD)$" && req.url ~ "\.(jpg|jpeg|gif|png|ico|css|zip|tgz|gz|rar|bz2|pdf|txt|tar|wav|bmp|rtf|js|flv|swf|html|htm)(\?.*)?$") {
        # if you use a subdomain for admin section, do not cache it
        #if (req.http.host ~ "admin.yourdomain.com") {
        #    set req.http.X-VC-Cacheable = "NO:Admin domain";
        #    return(pass);
        #}
        # enable this if you want
        #if (req.url ~ "debug") {
        #    set req.http.X-VC-Debug = "true";
        #}
        # enable this if you need it
        #if (req.url ~ "nocache") {
        #    set req.http.X-VC-Cacheable = "NO:Not cacheable, nocache in URL";
        #    return(pass);
        #}
        set req.url = regsub(req.url, "\?.*$", "");
        # unset cookie only if no http auth
        if (!req.http.Authorization) {
            unset req.http.Cookie;
        }
        return(hash);
    }
}

sub vcl_backend_response {
    if (bereq.method ~ "^(GET|HEAD)$" && bereq.url ~ "\.(jpg|jpeg|gif|png|ico|css|zip|tgz|gz|rar|bz2|pdf|txt|tar|wav|bmp|rtf|js|flv|swf|html|htm)$") {
        # overwrite ttl with X-VC-TTL
        set beresp.http.X-VC-TTL = 24*60*60;
        set beresp.ttl = std.duration(beresp.http.X-VC-TTL + "s", 0s);
        set beresp.http.X-VC-Cacheable = "YES:Is cacheable, ttl: " + beresp.ttl;
    }
}
