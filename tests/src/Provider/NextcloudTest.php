<?php

namespace Bahuma\OAuth2\Client\Tests\Provider;

use GuzzleHttp\ClientInterface;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Bahuma\OAuth2\Client\Provider\Nextcloud;
use Ramsey\Uuid\Uuid;
use Bahuma\OAuth2\Client\Provider\NextcloudResourceOwner;

class NextcloudTest extends TestCase
{
    /**
     * @var Nextcloud
     */
    protected $provider;

    protected function setUp(): void
    {
        $this->provider = new Nextcloud([
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri' => 'mock_redirect_uri',
        ]);
    }

    public function testAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        parse_str($uri['query'], $query);

        $this->assertArrayHasKey('client_id', $query);
        $this->assertArrayHasKey('redirect_uri', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('scope', $query);
        $this->assertArrayHasKey('response_type', $query);
        $this->assertArrayHasKey('approval_prompt', $query);
        $this->assertNotNull($this->provider->getState());
    }

    public function testResourceOwnerDetailsUrl(): void
    {
        $token = Mockery::mock(AccessToken::class);
        $url = $this->provider->getResourceOwnerDetailsUrl($token);
        $uri = parse_url($url);
        $this->assertEquals('/api/v1/oauth2/user/whoami', $uri['path']);
    }

    public function testGetAccessToken(): void
    {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getBody')->andReturn('{"access_token":"mock_access_token", "token_type":"bearer"}');
        $response->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('send')->times(1)->andReturn($response);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $this->assertEquals('mock_access_token', $token->getToken());
        $this->assertNull($token->getExpires());
        $this->assertNull($token->getRefreshToken());
        $this->assertNull($token->getResourceOwnerId());
    }

    /**
     * @expectedException \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    public function testExceptionThrownWhenErrorObjectReceived(): void
    {
        $message = uniqid();
        $status = rand(400, 600);
        $postResponse = Mockery::mock(ResponseInterface::class);
        $postResponse->shouldReceive('getBody')->andReturn(' {"error":"' . $message . '"}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn($status);
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('send')->once()->andReturn($postResponse);
        $this->provider->setHttpClient($client);
        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }

    public function testUserData(): void
    {
        $response_data = [
            'sub' => rand(1000, 9999),
            'name' => uniqid(),
            'email' => uniqid(),
        ];

        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn('{"access_token":"mock_access_token","expires_in":"3600","token_type":"Bearer","scope":"openid email profile","id_token":"mock_token_id"}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn(200);
        $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $userResponse->shouldReceive('getBody')->andReturn(json_encode($response_data));
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $userResponse->shouldReceive('getStatusCode')->andReturn(200);
        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
            ->times(2)
            ->andReturn($postResponse, $userResponse);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $user = $this->provider->getResourceOwner($token);

        $this->assertEquals($response_data['sub'], $user->getId());
        $this->assertEquals($response_data['sub'], $user->toArray()['sub']);
        $this->assertEquals($response_data['email'], $user->getEmail());
        $this->assertEquals($response_data['email'], $user->toArray()['email']);
        $this->assertEquals($response_data['name'], $user->getName());
        $this->assertEquals($response_data['name'], $user->toArray()['name']);
    }
}
