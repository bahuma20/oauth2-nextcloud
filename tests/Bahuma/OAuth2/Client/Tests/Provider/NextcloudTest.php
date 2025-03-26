<?php

namespace Bahuma\OAuth2\Client\Tests\Provider;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Utils;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Bahuma\OAuth2\Client\Provider\Nextcloud;

class NextcloudTest extends TestCase
{
    /**
     * @var Nextcloud
     */
    protected $provider;

    protected function setUp(): void
    {
//        $dumpPath = sys_get_temp_dir() . '/MockeryDump';
//        m::setLoader(new m\Loader\RequireLoader($dumpPath));

        parent::setUp();

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
        $token = m::mock(AccessToken::class);
        $url = $this->provider->getResourceOwnerDetailsUrl($token);
        $uri = parse_url($url);
        $this->assertEquals('/ocs/v2.php/cloud/user', $uri['path']);
    }

    public function testGetAccessToken(): void
    {
        $response = m::mock('Psr\Http\Message\ResponseInterface');
        $body = Utils::streamFor('{"access_token":"mock_access_token", "token_type":"bearer"}');
        $response->shouldReceive('getBody')->andReturn($body);
        $response->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $client = m::mock(ClientInterface::class);
        $client->shouldReceive('send')->times(1)->andReturn($response);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $this->assertEquals('mock_access_token', $token->getToken());
        $this->assertNull($token->getExpires());
        $this->assertNull($token->getRefreshToken());
        $this->assertNull($token->getResourceOwnerId());
    }

    public function testExceptionThrownWhenErrorObjectReceived(): void
    {
        $this->expectException(IdentityProviderException::class);
        $message = uniqid();
        $status = rand(400, 600);
        $postResponse = m::mock(ResponseInterface::class);
        $body = Utils::streamFor(' {"error":"' . $message . '"}');
        $postResponse->shouldReceive('getBody')->andReturn($body);
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn($status);
        $client = m::mock(ClientInterface::class);
        $client->shouldReceive('send')->once()->andReturn($postResponse);
        $this->provider->setHttpClient($client);
        $this->expectException(IdentityProviderException::class);
        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }

    public function testUserData(): void
    {
        $response_data = [
            'ocs' => [
                'data' => [
                    'id' => rand(1000, 9999),
                    'display-name' => uniqid(),
                    'email' => uniqid(),
                    'groups' => [uniqid(), uniqid()],
                ],
            ]
        ];
        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
        // @codingStandardsIgnoreStart
        $body = Utils::streamFor('{"access_token":"mock_access_token","expires_in":"3600","token_type":"Bearer","scope":"openid email profile","id_token":"mock_token_id"}');
        // @codingStandardsIgnoreEnd
        $postResponse->shouldReceive('getBody')->andReturn($body);
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn(200);
        $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $body2 = Utils::streamFor(json_encode($response_data));
        $userResponse->shouldReceive('getBody')->andReturn($body2);
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $userResponse->shouldReceive('getStatusCode')->andReturn(200);
        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
            ->times(2)
            ->andReturn($postResponse, $userResponse);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $user = $this->provider->getResourceOwner($token);
        $this->assertEquals($response_data['ocs']['data']['id'], $user->getId());
        $this->assertEquals($response_data['ocs']['data']['id'], $user->toArray()['id']);
        $this->assertEquals($response_data['ocs']['data']['email'], $user->getEmail());
        $this->assertEquals($response_data['ocs']['data']['email'], $user->toArray()['email']);
        $this->assertEquals($response_data['ocs']['data']['display-name'], $user->getName());
        $this->assertEquals($response_data['ocs']['data']['display-name'], $user->toArray()['display-name']);
        $this->assertEquals($response_data['ocs']['data']['groups'], $user->getGroups());
        $this->assertEquals($response_data['ocs']['data']['groups'], $user->toArray()['groups']);
    }
}
