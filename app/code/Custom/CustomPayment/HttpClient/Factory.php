<?php
namespace Custom\CustomPayment\HttpClient;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\HttpClient;

class Factory
{
    public function createClient(): HttpClientInterface
    {
        return HttpClient::create();
    }
}
