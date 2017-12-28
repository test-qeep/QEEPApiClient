<?php

namespace QEEP\QEEPApiClient\V2;


use JMS\Serializer\SerializerBuilder;
use QEEP\QEEPApiClient\V2\Model\Category;
use QEEP\QEEPApiClient\V2\Model\CustomQuestion;
use QEEP\QEEPApiClient\V2\Model\Option;
use QEEP\QEEPApiClient\V2\Model\Parameter;
use QEEP\QEEPApiClient\V2\Model\Product;
use QEEP\QEEPApiClient\V2\Model\Variant;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class ApiClient
{
    const HTTP_GET = 'HTTP_GET';
    const HTTP_POST = 'HTTP_POST';

    const API_ROUTE_PREFIX = '/api/v2/';

    const API_IMAGE_PREFIX = 'images.';

    private $clientId;

    private $clientSecret;

    private $url;

    private $salesChannel;

    private $imageUrl;

    private $serializer;

    private $jms;

    public function __construct(
        int $clientId,
        string $clientSecret,
        string $crmUrl,
        string $salesChannel,
        string $imageUrl
    )
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->url = $crmUrl;
        $this->salesChannel = $salesChannel;
        $this->imageUrl = $imageUrl;

        $this->serializer = new Serializer(
            [new ObjectNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()]
        );

        $this->jms = SerializerBuilder::create()->build();
    }

    /** @return Category[] */
    public function getCategories() : array
    {
        return $this->deserializeArray(
            $this->callApiV2Method('categories/get'),
            Category::class
        );
    }

    // TODO: make denoramalize recursive
    /** @return Product[] */
    public function getProducts() : array
    {
        $rawProducts = $this->callApiV2Method('products/get');

        $products = [];
        foreach ($rawProducts as $rawProduct) {
            /** @var Product $product */
            $product = $this
                ->serializer
                ->denormalize($rawProduct, Product::class);

            $options = [];
            foreach ($product->getOptions() as $rawOption) {
                $options[] = $this
                    ->serializer
                    ->denormalize($rawOption, Option::class);
            }
            $product->setOptions($options);

            $variants = [];
            foreach ($product->getVariants() as $rawVariant) {
                /** @var Variant $variant */
                $variant = $this
                    ->serializer
                    ->denormalize($rawVariant, Variant::class);

                $parameters = [];
                foreach ($variant->getParameters() as $rawParameter) {
                    $parameters[] = $this
                        ->serializer
                        ->denormalize($rawParameter, Parameter::class);
                }
                $variant->setParameters($parameters);

                $variants[] = $variant;
            }
            $product->setVariants($variants);

            $products[] = $product;
        }

        return $products;
    }

    /** @return CustomQuestion[] */
    public function getCustomQuestions() : array
    {
        $rawQuestions = $this->callApiV2Method('questions/get');

        $questions = [];
        foreach ($rawQuestions as $rawQuestion) {
            $questions[] = $this
                ->serializer
                ->denormalize($rawQuestion, CustomQuestion::class);
        }

        return $questions;
    }

    /** @return string[] */
    public function getCities() : array
    {
        return $this->callApiV2Method('cities/get');
    }

    private function callApiV2Method(
        string $urlSuffix,
        array $params = [],
        string $method = self::HTTP_GET
    )
    {
        $params = http_build_query($params + $this->getAuthParams($params));
        $ch = curl_init();
        $url = $this->url . self::API_ROUTE_PREFIX . $urlSuffix;

        switch ($method) {
            case self::HTTP_GET:
                if ($params) {
                    $url .= '?' . $params;
                }
                break;
            case self::HTTP_POST:
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                break;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error || $code != 200) {
            throw new ApiException($error, $code, $params);
        }

        return json_decode($response, true);
    }

    private function deserializeArray(array $rawEntities, string $className) : array
    {
        $jms = $this->jms;

        return array_map(
            function ($entity) use ($jms, $className) {
                return $jms->fromArray($entity, $className);
            },
            $rawEntities
        );
    }

    private function getAuthParams(array $params) : array
    {
        ksort($params);
        $paramsString = '';
        foreach ($params as $param) {
            if (is_scalar($param)) {
                $paramsString .= $param;
            }
        }

        return [
            'client_id' => $this->clientId,
            'access_token' => md5($this->clientId . $this->clientSecret . $paramsString),
        ];
    }
}
