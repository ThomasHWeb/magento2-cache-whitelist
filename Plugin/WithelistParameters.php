<?php

namespace ThomasHWeb\CacheWhitelist\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Http\Context;
use Magento\Framework\App\PageCache\Identifier;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\Serialize\Serializer\Json;

class WithelistParameters
{
    private $request;
    private $context;
    private $serializer;
    private $scopeConfig;

    public function __construct(
        Http $request,
        ScopeConfigInterface $scopeConfig,
        Context $context,
        Json $serializer
    ) {
        $this->request = $request;
        $this->context = $context;
        $this->serializer = $serializer;
        $this->scopeConfig = $scopeConfig;
    }

    public function afterGetValue(Identifier $subject, string $result): string
    {
        if (!$this->isWhitelistEnable()) {
            return $result;
        }

        $allowedParameters = $this->getParametersInWhitelist();

        if (empty($allowedParameters)) {
            return $result;
        }

        $uri = $this->getUrl();

        foreach ($this->request->getParams() as $key => $value) {
            if (!in_array($key, $allowedParameters, true)) {
                $uri .= ($uri === $this->getUrl()) ? '?' : '&';
                $uri .= $key . '=' . $value;
            }
        }

        return $this->encodeData($uri);
    }

    private function getParametersInWhitelist(): array
    {
        $keys = $this->scopeConfig->getValue('system/full_page_cache/cache_whitelist/keys');

        if (empty($keys)) {
            return [];
        }

        return explode(',', $keys);
    }

    private function encodeData(string $uri): string
    {
        return sha1($this->serializer->serialize([
            $this->request->isSecure(),
            $uri,
            $this->request->get(ResponseHttp::COOKIE_VARY_STRING) ?? $this->context->getVaryString()
        ]));
    }

    private function isWhitelistEnable(): bool
    {
        return (bool)$this->scopeConfig->getValue('system/full_page_cache/cache_whitelist/enable');
    }

    private function getUrl(): string
    {
        return $this->request->getUri()->getScheme()
            . '://' . $this->request->getUri()->getHost()
            . $this->request->getBasePath();
    }
}
