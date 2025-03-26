# Nextcloud Provider for OAuth 2.0 Client

This package provides Nextcloud OAuth 2.0 support for the PHP League's [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

This package is compliant with [PSR-1][], [PSR-2][] and [PSR-4][]. If you notice compliance oversights, please send
a patch via pull request.

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md

## Requirements

The following versions of PHP are supported.

* From PHP 7.4 to PHP 8.4

To use this package, it will be necessary to have a Nextcloud client ID and client
secret. These are referred to as `{nextcloud-client-id}` and `{nextcloud-client-secret}`
in the documentation.

Please follow the [Nextcloud instructions][oauth-setup] to create the required credentials.

[oauth-setup]: https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/oauth2.html#add-an-oauth2-application

## Installation

To install, use composer:

```sh
composer require bahuma/oauth2-nextcloud
```

## Usage

### Authorization Code Flow

```php
use Bahuma\OAuth2\Client\Provider\Nextcloud;

session_start();

$provider = new Nextcloud([
    'clientId'     => '{nextcloud-client-id}',
    'clientSecret' => '{nextcloud-client-secret}',
    'redirectUri'  => 'https://example.com/callback-url',
    'nextcloudUrl' => 'https://cloud.example.com', // Base URL of your nextcloud instance.
]);

if (!empty($_GET['error'])) {

    // Got an error, probably user denied access
    exit('Got error: ' . htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'));

} elseif (empty($_GET['code'])) {

    // If we don't have an authorization code then get one
    $authUrl = $provider->getAuthorizationUrl();
    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: ' . $authUrl);
    exit;

} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {

    // State is invalid, possible CSRF attack in progress
    unset($_SESSION['oauth2state']);
    exit('Invalid state');

} else {

    // Try to get an access token (using the authorization code grant)
    $token = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code']
    ]);

    // Optional: Now you have a token you can look up a users profile data
    try {

        // We got an access token, let's now get the owner details
        /** @var \Bahuma\OAuth2\Client\Provider\NextcloudResourceOwner $ownerDetails */
        $ownerDetails = $provider->getResourceOwner($token);

        // Use these details to create a new profile
        printf('Hello %s!', $ownerDetails->getEmail());

    } catch (Exception $e) {

        // Failed to get user details
        exit('Something went wrong: ' . $e->getMessage());

    }

    // Use this to interact with an API on the users behalf
    echo $token->getToken();

    // Use this to get a new access token if the old one expires
    echo $token->getRefreshToken();

    // Unix timestamp at which the access token expires
    echo $token->getExpires();
}
```

### Refreshing a Token

```php
$token = $provider->getAccessToken('authorization_code', [
    'code' => $code
]);

// persist the token in a database
$refreshToken = $token->getRefreshToken();
```

Now you have everything you need to refresh an access token using a refresh token:

```php
use Bahuma\OAuth2\Client\Provider\Nextcloud;
use League\OAuth2\Client\Grant\RefreshToken;

$provider = new Nextcloud([
    'clientId'     => '{google-client-id}',
    'clientSecret' => '{google-client-secret}',
    'redirectUri'  => 'https://example.com/callback-url',
    'nextcloudUrl' => 'https://cloud.example.com', // Base URL of your nextcloud instance.
]);

$grant = new RefreshToken();
$token = $provider->getAccessToken($grant, ['refresh_token' => $refreshToken]);
```

## Scopes

Nextcloud OAuth2 implementation currently does not support scoped access. This means that every token has full access 
to the complete account including read and write permission to the stored files. It is essential to store the OAuth2 
tokens in a safe way!

## Testing

Tests can be run with:

```sh
composer test
```

Style checks can be run with:

```sh
composer check
```


## Credits

- [Max Bachhuber](https://github.com/bahuma20)
- [Aleix Quintana Alsius](https://github.com/aleixq) 
- [All Contributors](https://github.com/bahuma/oauth2-nextcloud/contributors)


## License

The MIT License (MIT). Please see [License File](https://github.com/thephpleague/oauth2-nextcloud/blob/master/LICENSE) for more information.
