# cloudflare.vcl -- CloudFlare HTTP Headers

# This should generally be loaded first to make sure that the headers
# get set appropriately for all requests.

acl official_cloudflare {
    # https://www.cloudflare.com/ips-v4
    "204.93.240.0"/24;
    "204.93.177.0"/24;
    "199.27.128.0"/21;
    "173.245.48.0"/20;
    "103.21.244.0"/22;
    "103.22.200.0"/22;
    "103.31.4.0"/22;
    "141.101.64.0"/18;
    "108.162.192.0"/18;
    "190.93.240.0"/20;
    "188.114.96.0"/20;
    "197.234.240.0"/22;
    "198.41.128.0"/17;
    "162.158.0.0"/15;
    # https://www.cloudflare.com/ips-v6
    "2400:cb00::"/32;
    "2606:4700::"/32;
    "2803:f800::"/32;
    "2405:b500::"/32;
    "2405:8100::"/32;
}

sub vcl_recv {
    # Set the CF-Connecting-IP header
    # If the client.ip is trusted, we leave the header alone if present.
    if (req.http.CF-Connecting-IP) {
        if (client.ip !~ official_cloudflare && client.ip !~ cloudflare) {
            set req.http.CF-Connecting-IP = client.ip;
        }
    } else {
        set req.http.CF-Connecting-IP = client.ip;
    }
}
