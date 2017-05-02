# mobile_pass.vcl -- Mobile pass-through support for Varnish

# This simply bypasses the cache for anything that looks like a mobile
# (or tablet) device.
# Also passes through some requests that are specifically for the WordPress
# Jetpack mobile plugin.

sub vcl_recv {
    # Rules specifically for the Jetpack Mobile module
    if (req.url ~ "\?(.*&)?(ak_action|app-download)=") {
        return(pass);
    }
    if (req.http.Cookie ~ "(^|;\s*)akm_mobile=") {
        return(pass);
    }

    # General User-Agent blacklist (anything that remotely looks like a mobile device)
    if (req.http.User-Agent ~ "(?i)ipod|android|blackberry|phone|mobile|kindle|silk|fennec|tablet|webos|palm|windows ce|nokia|philips|samsung|sanyo|sony|panasonic|ericsson|alcatel|series60|series40|opera mini|opera mobi|au-mic|audiovox|avantgo|blazer|danger|docomo|epoc|ericy|i-mode|ipaq|midp-|mot-|netfront|nitro|pocket|portalmmm|rover|sie-|symbian|cldc-|j2me|up\.browser|up\.link|vodafone|wap1\.|wap2\.") {
        return(pass);
    }
}
