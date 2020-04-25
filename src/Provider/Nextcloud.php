<?php

namespace Bahuma\Oauth2\Client\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

class Nextcloud extends AbstractProvider
{
  use BearerAuthorizationTrait;

  /**
   * @var string Base URL of the nextcloud instance (not including trailing slash).
   */
  protected $nextcloudUrl = '';

  public function getBaseAuthorizationUrl()
  {
    return $this->nextcloudUrl . '/apps/oauth2/authorize';
  }

  public function getBaseAccessTokenUrl(array $params)
  {
    return $this->nextcloudUrl . '/apps/oauth2/api/v1/token';
  }

  public function getResourceOwnerDetailsUrl(AccessToken $token)
  {
    return $this->nextcloudUrl . '/ocs/v2.php/cloud/user?format=json';
  }

  protected function getDefaultScopes()
  {
    return [

    ];
  }

  protected function checkResponse(ResponseInterface $response, $data)
  {
    // @codeCoverageIgnoreStart
    if (empty($data['error'])) {
      return;
    }
    // @codeCoverageIgnoreEnd

    $code = 0;
    $error = $data['error'];

    if (is_array($error)) {
      $code = $error['code'];
      $error = $error['message'];
    }

    throw new IdentityProviderException($error, $code, $data);
  }

  protected function createResourceOwner(array $response, AccessToken $token)
  {
    return new NextcloudResourceOwner($response);
  }
}
