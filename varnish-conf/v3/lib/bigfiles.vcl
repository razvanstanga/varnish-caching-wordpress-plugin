# bigfiles.vcl -- Bypass Cache for Large Files

sub vcl_fetch {
    # Bypass cache for files > 10 MB
    if (std.integer(beresp.http.Content-Length, 0) > 10485760) {
        return (hit_for_pass);
    }
}
