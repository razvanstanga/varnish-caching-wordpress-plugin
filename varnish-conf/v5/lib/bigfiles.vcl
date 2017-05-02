# bigfiles.vcl -- Bypass Cache for Large Files

sub vcl_backend_response {
    # Bypass cache for files > 10 MB
    if (std.integer(beresp.http.Content-Length, 0) > 10485760) {
        set beresp.uncacheable = true;
        set beresp.ttl = 120s;
        return (deliver);
    }
}
