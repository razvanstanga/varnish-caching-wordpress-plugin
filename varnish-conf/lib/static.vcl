# static.vcl -- Static File Caching for Varnish

sub vcl_recv {
    if (req.request ~ "^(GET|HEAD)$" && req.url ~ "\.(jpg|jpeg|gif|png|ico|css|zip|tgz|gz|rar|bz2|pdf|txt|tar|wav|bmp|rtf|js|flv|swf|html|htm)(\?.*)?$") {
        # disable this if you want
        if (req.url ~ "nocache") {
            return(pass);
        }
        set req.url = regsub(req.url, "\?.*$", "");
        # unset cookie only if no http auth
        if (!req.http.Authorization) {
            unset req.http.Cookie;
        }
        set req.grace = 2m;
        return(lookup);
    }
}

sub vcl_fetch {
    if (req.request ~ "^(GET|HEAD)$" && req.url ~ "\.(jpg|jpeg|gif|png|ico|css|zip|tgz|gz|rar|bz2|pdf|txt|tar|wav|bmp|rtf|js|flv|swf|html|htm)$") {
        # overwrite ttl with X-VC-TTL
        set beresp.http.X-VC-TTL = "24";
        set beresp.ttl = std.duration(beresp.http.X-VC-TTL + "h", 0s);
        set beresp.grace = 2m;
    }
}
