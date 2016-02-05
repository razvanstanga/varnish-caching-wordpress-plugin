# mobile_cache.vcl -- Separate cache for mobile clients

# If the User-Agent looks like a mobile device, then we add the string
# "mobile" to the hash_data.  This results in mobile devices having
# a separate cache from non-mobile devices.
#
# Note that if the backend does anything more sophisticated than having
# a "desktop" and a "mobile" version of pages (for example serving one
# page to iPhones and another to Androids), this will not be desirable.
# Also if the backend disagrees with this logic as far as what is a
# "mobile" User-Agent, then we may save the wrong version of pages in
# the cache.

sub vcl_hash {
    # General User-Agent list (anything that remotely looks like a mobile device)
    if (req.http.User-Agent ~ "(?i)ipod|android|blackberry|phone|mobile|kindle|silk|fennec|tablet|webos|palm|windows ce|nokia|philips|samsung|sanyo|sony|panasonic|ericsson|alcatel|series60|series40|opera mini|opera mobi|au-mic|audiovox|avantgo|blazer|danger|docomo|epoc|ericy|i-mode|ipaq|midp-|mot-|netfront|nitro|pocket|portalmmm|rover|sie-|symbian|cldc-|j2me|up\.browser|up\.link|vodafone|wap1\.|wap2\.") {
        hash_data("mobile");
    }
}
