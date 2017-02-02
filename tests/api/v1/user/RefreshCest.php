<?php

namespace tests\api\v1\user;

use tests\_support\AbstractApiCest;
use Yii;

class RefreshCest extends AbstractApiCest
{
    protected $uri = '/api/v1/user/refresh';
    protected $blockedVerbs = ['put', 'get', 'patch', 'delete'];
    protected $allowedVerbs = ['post'];

    public function testRefreshWithValidToken(\ApiTester $I)
    {
        $I->register(true);
        $I->wantTo('verify refresh token renews session token');
        $payload = [
            'refresh_token' => $I->getTokens()['refresh_token']
        ];

        $I->sendAuthenticatedRequest($this->uri, 'POST', $payload);

        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);
        $I->seeResponseMatchesJsonType([
            'data' => [
                'access_token' => 'string',
                'refresh_token' => 'string',
                'ikm' => 'string',
                'expires_at' => 'integer'
            ],
            'status' => 'integer'
        ]);
    }
    
    public function testRefreshWithInvalidToken(\ApiTester $I)
    {
        $I->register(true);
        $I->wantTo('verify refresh token does not renew with invalid tokens');
        $payload = [
            'refresh_token' => $I->getTokens()['access_token']
        ];

        $I->sendAuthenticatedRequest($this->uri, 'POST', $payload);

        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(200);

        $I->seeResponseContainsJson([
            'status' => 200,
            'data' => false,
        ]);
    }

    public function testAuthenticationIsRequired(\ApiTester $I)
    {
        $I->wantTo('verify authentication is required');
        $I->sendPOST($this->uri);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(401);
    }
}
