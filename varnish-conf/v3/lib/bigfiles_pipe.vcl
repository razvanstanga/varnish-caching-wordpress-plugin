# bigfiles_pipe.vcl -- Pipe for Large Files

# You must have "import std;" in your main vcl:
# import std;

# NOTE: Using restart and pipe is a workaround for a bug in varnish prior to
# 3.0.3.  In 3.0.3+, hit_for_pass in vcl_fetch is all that is necessary.

sub vcl_recv {
    if (req.http.X-Pipe-Big-File && req.restarts > 0) {
        unset req.http.X-Pipe-Big-File;
        return (pipe);
    }
}

sub vcl_fetch {
    # Bypass cache for files > 10 MB
    if (std.integer(beresp.http.Content-Length, 0) > 10485760) {
        set req.http.X-Pipe-Big-File = "Yes";
        return (restart);
    }
}
