<?php

namespace Ae\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class MailchimpResourceOwner implements ResourceOwnerInterface
{
    /** @var array */
    private $response;
    
    public function __construct(array $response = [])
    {
        $this->response = $response;
    }

    public function getId()
    {
       return $this->response['user_id'];
    }

    public function toArray()
    {
        return $this->response;
    }

    public function getDc()
    {
        return $this->response['dc'];
    }

    public function getAccountName()
    {
        return $this->response['accountname'];
    }

    public function getApiEndpoint()
    {
        return $this->response['api_endpoint'];
    }
}
