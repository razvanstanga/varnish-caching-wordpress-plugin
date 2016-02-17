acl cloudflare {
    # set this ip to your Railgun IP (if applicable)
    # "1.2.3.4";
}

acl purge {
    "localhost";
    "127.0.0.1";
    #"192.168.0.2";
}