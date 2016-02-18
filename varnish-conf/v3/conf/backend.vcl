backend backend1 {
    .host = "192.168.0.2";
    .port = "80";
}

director backends round-robin {
    {
        .backend = backend1;
    }
}

sub vcl_recv {
    set req.backend = backends;
}