<?php
namespace Ae\OAuth2\Client\Test\Provider;

use Ae\OAuth2\Client\Provider\Mailchimp;
use GuzzleHttp\ClientInterface;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Tool\QueryBuilderTrait;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class MailchimpTest extends TestCase
{
    use QueryBuilderTrait;

    /**
     * @var Mailchimp
     */
    protected $provider;

    /**
     * @var string
     */
    protected $accessTokenResponse;

    protected function setUp()
    {
        $this->provider = new Mailchimp([
            'clientId' => 'client_id',
            'clientSecret' => 'client_secret',
            'redirectUri' => 'http://return_url.test',
        ]);
        $this->accessTokenResponse = json_encode([
            'access_token' => 'access_token',
            'expires_in' => 0,
            'scope' => null
        ]);
    }

    public function testAuthorizationUrl()
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        parse_str($uri['query'], $query);

        $this->assertEquals('/oauth2/authorize', $uri['path']);
        $this->assertArrayHasKey('client_id', $query);
        $this->assertArrayHasKey('redirect_uri', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('scope', $query);
        $this->assertArrayHasKey('response_type', $query);
        $this->assertArrayHasKey('approval_prompt', $query);
        $this->assertNotNull($this->provider->getState());
    }

    public function testScopes()
    {
        $scopeSeparator = ',';
        $options = ['scope' => [uniqid('', true), uniqid('', true)]];
        $query = ['scope' => implode($scopeSeparator, $options['scope'])];
        $url = $this->provider->getAuthorizationUrl($options);
        $encodedScope = $this->buildQueryString($query);
        $this->assertContains($encodedScope, $url);
    }

    public function testGetBaseAccessTokenUrl()
    {
        $params = [];

        $url = $this->provider->getBaseAccessTokenUrl($params);
        $uri = parse_url($url);

        $this->assertEquals('/oauth2/token', $uri['path']);
    }

    public function testGetAccessToken()
    {
        $response = $this->getMockBuilder(ResponseInterface::class)->getMock();

        $response->expects($this->once())->method('getBody')->willReturn($this->accessTokenResponse);
        $response->expects($this->once())->method('getHeader')->willReturn(['content-type' => 'json']);

        $client = $this->getMockBuilder(ClientInterface::class)->getMock();
        $client->expects($this->once())->method('send')->willReturn($response);

        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

        $this->assertEquals('access_token', $token->getToken());
        $this->assertEquals(2147483647, $token->getExpires());
        $this->assertNull($token->getRefreshToken());
    }

    public function testUserData()
    {
        $name = uniqid('', true);
        $userId = mt_rand(1000,9999);

        $results = [
            'user_id' => $userId,
            'accountname' => $name,
            'dc' => 'us10',
            'api_endpoint' => 'http://fakeurl'
        ];

        $response = $this->getMockBuilder(ResponseInterface::class)->getMock();
        $response->expects($this->once())->method('getBody')->willReturn($this->accessTokenResponse);
        $response->expects($this->once())->method('getHeader')->willReturn(['content-type' => 'json']);

        $userResponse = $this->getMockBuilder(ResponseInterface::class)->getMock();

        $userResponse->expects($this->once())->method('getBody')->willReturn(json_encode($results));
        $userResponse->expects($this->once())->method('getHeader')->willReturn(['content-type' => 'json']);

        $client = $this->getMockBuilder(ClientInterface::class)->getMock();
        $client->expects($this->exactly(2))->method('send')->will(
            $this->onConsecutiveCalls( $response, $userResponse )
        );

        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $user = $this->provider->getResourceOwner($token);

        $this->assertEquals($userId, $user->getId());
        $this->assertEquals($userId, $user->toArray()['user_id']);
        $this->assertEquals($name, $user->getAccountName());
        $this->assertEquals('http://fakeurl', $user->getApiEndpoint());
        $this->assertEquals('us10', $user->getDc());

        $this->assertEquals($results, $user->toArray());
    }

    public function testExceptionThrownWhenErrorObjectReceived()
    {
        $message = uniqid('', true);

        $this->expectException(IdentityProviderException::class);
        $this->expectExceptionMessage($message);

        $response = $this->getMockBuilder(ResponseInterface::class)->getMock();
        $response->expects($this->once())->method('getBody')->willReturn('{"error": "'.$message.'"}');
        $response->expects($this->once())->method('getHeader')->willReturn(['content-type' => 'json']);

        $client = $this->getMockBuilder(ClientInterface::class)->getMock();
        $client->expects($this->once())->method('send')->willReturn($response);

        $this->provider->setHttpClient($client);

        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }
}