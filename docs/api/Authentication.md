# Authentication

> This documentation is specifically for the `yrc\filters\HMACSignatureAuth`  authentication behavior. If you are not using behavior, this documentation does not apply to you. This document also assumes the default authentication endpoint of `/api/v1/user/authenticate`

To authenticate into the API, you simply need to provide a valid email address and password pair as a JSON request to `/api/v1/user/authenticate`.

```json
POST /api/v1/user/authenticate
{
    "email": "clara.oswald@tardis.io",
    "password": "correct horse battery stable"
}
```

If two-factor authentication is enabled, a third parameter, `otp` is required, which should be the string value of the TOTP code provided by the user's authenticator app.

```json
POST /api/v1/user/authenticate
{
    "email": "clara.oswald@tardis.io",
    "password": "correct horse battery stable",
    "otp": "615214"
}
```

If the username or password is not valid, an HTTP 401 will be returned with the following response body:

```json
{
    "data": null,
    "error": {
        "message": "",
        "code": 0
    },
    "status": 401
}
```

> Note, if the `otp` code is _not_ provided, but is enabled for the user, the error code will be set to `1`. In this instance the client _should_ retain the username and password from the request, and prompt the user for their `otp` code and relay the request with the `otp` code in the request body.

For successful authentications, you'll be presented with the following response body. The attributes outlined in this section are necessary to provide authentication for all other API endpoints that require authentication:

```json
{
    "data": {
        "access_token": "7XF56VIP7ZQQOLGHM6MRIK56S2QS363ULNB5UKNFMJRQVYHQH7IA",
        "refresh_token": "MA2JX5FXWS57DHW4OIHHQDCJVGS3ZKKFCL7XM4GNOB567I6ER4LQ",
        "ikm": "bDEyECRvKKE8w81fX4hz/52cvHsFPMGeJ+a9fGaVvWM=",
        "signing": "ecYXfAwNVoS9ePn4xWhiJOdXQzr6LpJIeIn4AVju/Ug=",
        "hash": "822d1a496b11ce6639fec7a2993ba5c02153150e45e5cec5132f3f16bfe95149",
        "expires_at": 1472678411
    },
    "status": 200
}
```

The `access_token` parameter is, until the `expires_at` time is reached, a unique token representing the user's identity. This is how the API will uniquely identify your user for all future sessions with the API (until the `expires_at` time is reached).

The `refresh_token` is a special token that enables the client to extend their sessions past the `expires_at` time. More information about how to refresh your tokens can be found in the relevant documentation for the refresh endpoint `/api/v1/user/refresh`.

The `ikm` parameter is the `Initial Key Material` which is used to seed the Hash-Based Key Derivation Function (HKDF), which is used to generate your `Authorization` headers (more information below). This value is 32 random bytes, base64 encoded. On your client you _must_ base64 decode this value to work with it within HKDF.

The `signing` element is a 32 byte `libsodium` signature public key, which can be used to decrypt responses. While the `hash` element is a unique ID you can use to reference a specific cryptographic key on the API for `application/vnd.25519+json` requests.

### Generating HMAC Signatures

Once you have received the aforementioned values from the `/api/v1/user/authenticate` API endpoint, you can construct your HMAC `Authorization` header using HKDF.


The Authentication header is composed of 3 components:

1. The `access_token` provided by the API.
2. The base64 encoded HMAC-SHA256 signature
3. The base64 encoded client generated salt value

> The base64 encoded client generate salt should be 32 bytes, and should be generated for each request.

The following algorithm is applied to generate the HMAC value.

#### Version 2

Version 2 if the HMAC header provides the following additional features:

1. Removes reproducible SHA signature.
   While SHA-256 is not likely to be broken any time soon, with the version 1 header, an adversary would be easily able to collect hashes of the raw request body to perform analysis on. The version 2 header removes this vulnerability by using libsodium's `crypto_generichash()` function for calculate the payload signature
2. Consolodates HMAC header
   The HMAC header is now a single base64 encoded JSON string containing all necessary information, as opposed to a comma separate list of elements. This makes library generation and consumption easier

```
salt = <32 random bytes>
auth_info = "HMAC|AuthenticationKey"

date = RFC1123_DATE
signature =
    BASE64(SODIUM_CRYPTO_GENERICHASH(REQUEST_BODY, <SALT>, 64))\n
    REQUEST_TYPE+REQUEST_URI\n
    <date>\n
    <base64_client_generated_salt>

hkdf = HKDF-SHA256(<IKM>, <SALT>, <AUTH_INFO>, 0),

hmac = base64(
    HMAC-SHA256(
        signature,
        hkdf,
    )
)

hmac-header = BASE64(
    JSON([
        'hmac' => BASE64(<hmac>),
        'salt' => BASE64(<salt>),
        'v' => 2,
        'access_token' => <access-token>
        'date' => <date>
    ])
)
```

## Making Authentication Requests

To make authenticated requests to the API you need to submit to special headers to the API, `X-Date`, and `Authorization`

```
Header:
    Authorization: HMAC eyJobWFjIjoidEtFNFRrSHcyMXRQR241VHltN1lTR2VWV09sQmJaU3dmZ3d1WDRMdlFZcz0iLCJhY2Nlc3NfdG9rZW4iOiIzV1wvTEgzQXZpZEEycS5oblRuLkdWbTlGbVwvQ2FKNUtuIiwic2FsdCI6IlJXMDJsR28rWnEyNGxoMjNPY3JlWkw5U3d6WHpJdHhWZjBxMUJsMTNxRFU9IiwiZGF0ZSI6IlRodSwgMDIgQXVnIDIwMTggMTk6MzE6MzcgKzAwMDAiLCJ2IjoxfQ==
```

### Date time drift

The `date` header is a [RFC1123](https://tools.ietf.org/html/rfc1123) formatted date, and exists to prevent certain relay attacks (an adversary gaining access to the authentication information and replaying it). Consequently, if the date header drifts more than 90 seconds from the server time, the request will be denied.

#### Version 1

The legacy HMAC header is generated as follows.

```
salt = <32 random bytes>
auth_info = "HMAC|AuthenticationKey"

signature =
    SHA256(REQUEST_BODY)\n
    REQUEST_TYPE+REQUEST_URI\n
    RFC1123_DATE\n
    <base64_client_generated_salt>

hkdf = HKDF-SHA256(<IKM>, <SALT>, <AUTH_INFO>, 0),

hmac = base64(
    HMAC-SHA256(
        signature,
        hkdf,
    )
)
```

These three values are then paired as follows to construct the `Authorization` header:

```
<access_token>,<HMAC>,<base64_client_generated_salt>
```

The ```hkdf``` hash should be returned as raw bytes and the ```SHA256(REQUEST_BODY)``` hash should be returned as a hex string to from the signature.

## Making Authentication Requests

To make authenticated requests to the API you need to submit to special headers to the API, `X-Date`, and `Authorization`

```
Header:
    X-DATE: 2016-04-16 15:26:00+00:00
    Authorization: HMAC 3W/LH3AvidA2q.hnTn.GVm9Fm/CaJ5Kn,tKE4TkHw21tPGn5Tym7YSGeVWOlBbZSwfgwuX4LvQYs=,RW02lGo+Zq24lh23OcreZL9SwzXzItxVf0q1Bl13qDU=
```

### Date time drift

The ```X-DATE``` header is a [RFC1123](https://tools.ietf.org/html/rfc1123) formatted date, and exists to prevent certain relay attacks (an adversary gaining access to the authentication information and replaying it). Consequently, if the date header drifts more than 90 seconds from the server time, the request will be denied.