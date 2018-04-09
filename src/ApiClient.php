<?php

namespace QEEP\QEEPApiClient;

use QEEP\QEEPApiClient\Model\Order;
use QEEP\QEEPApiClient\Model\OrderStatus;
use QEEP\QEEPApiClient\Model\Question;
use QEEP\QEEPApiClient\Model\Tag;
use QEEP\QEEPApiClient\Model\Product;
use QEEP\QEEPApiClient\Model\Setting;
use QEEP\QEEPApiClient\Model\Article;
use QEEP\QEEPApiClient\Model\ArticleType;
use QEEP\QEEPApiClient\Model\Brand;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class ApiClient
{
    const API_ROUTE_PREFIX = '/api/';

    const API_IMAGE_PREFIX = 'images.';

    private $clientId;

    private $clientSecret;

    private $url;

    private $salesChannel;

    private $imageUrl;

    private $serializer;

    public function __construct(
        int $clientId,
        string $clientSecret,
        string $crmUrl,
        string $salesChannel,
        string $imageUrl
    ) {
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
        $this->url          = $crmUrl;
        $this->salesChannel = $salesChannel;
        $this->imageUrl     = $imageUrl;

        $this->serializer = new Serializer(
            [new ObjectNormalizer(null, new CamelCaseToSnakeCaseNameConverter())],
            [new JsonEncoder()]
        );
    }

    public function createOrder(Order $order): string
    {
        $order->setSalesChannel($this->salesChannel);

        $response =  $this->callApiV1Method(
            self::API_ROUTE_PREFIX . 'orders.json/createOrder',
            Order::class,
            $this->serializer->normalize($order),
            'POST');

        if ('success' === $response['status']) {
            return 'success';
        } else {
            throw new ApiException();
        }
    }

    public function getOrderStatusCode(int $orderNumber): string
    {
        $status = $this->callApiV1Method(
            self::API_ROUTE_PREFIX . 'orders.json/getStatus',
            Order::class,
            ['order_id' => $orderNumber]
        );

        return $status['status'];
    }

    /** @return OrderStatus[] */
    public function getOrdersStatuses(): array
    {
        $rawStatuses = $this->callApiV1Method(
            self::API_ROUTE_PREFIX . 'list.json/getStatuses',
            OrderStatus::class
        );

        $statuses = [];
        foreach ($rawStatuses as $rawStatus) {
            $statuses[] = $this
                ->serializer
                ->denormalize($rawStatus, OrderStatus::class);
        }

        return $statuses;
    }

    /** @return Product[] */
    public function getProducts(): array
    {
        $rawProducts = $this->callApiV1Method(
            self::API_ROUTE_PREFIX . 'catalog.json/getAll',
            Product::class
        );

        $products = [];
        foreach ($rawProducts as $rawProduct) {
            $products[] = $this
                ->serializer
                ->denormalize($rawProduct, Product::class);
        }

        return $products;
    }

    /** @return Tag[] */
    public function getTags(): array
    {
        $rawTags = $this->callApiV1Method(
            self::API_ROUTE_PREFIX . 'list.json/getCategories',
            Tag::class
        );

        $tags = [];
        foreach ($rawTags as $rawTag) {
            $tags[] = $this
                ->serializer
                ->denormalize($rawTag, Tag::class);
        }

        return $tags;
    }

    public function getQuestions(): array
    {
        $rawQuestions = $this->callApiV1Method(
            self::API_ROUTE_PREFIX . 'list.json/getQuestions',
            Question::class
        );

        $questions = [];
        foreach ($rawQuestions as $rawQuestion) {
            $questions[] = $this
                ->serializer
                ->denormalize($rawQuestion, Question::class);
        }

        return $questions;
    }

    /** @return ArticleType[] */
    public function getArticleTypes(): array
    {
        /** @var array $rawArticleTypes */
        $rawArticleTypes = $this->callApiV1Method(
            self::API_ROUTE_PREFIX . 'articles.json/getTypes',
            ArticleType::class
        );

        $articleTypes = [];
        foreach ($rawArticleTypes as $rawArticleType) {
            $articleTypes[] = $this
                ->serializer
                ->denormalize($rawArticleType, ArticleType::class);
        }

        return $articleTypes;
    }

    /** @return Article[] */
    public function getArticles(): array
    {
        /** @var array $rawArticles */
        $rawArticles = $this->callApiV1Method(
            self::API_ROUTE_PREFIX . 'articles.json/getAll',
            Article::class
        );

        $articles = [];
        foreach ($rawArticles as $rawArticle) {
            $articles[] = $this
                ->serializer
                ->denormalize($rawArticle, Article::class);
        }

        return $articles;
    }

    /** @return Setting[] */
    public function getSettings(): array
    {
        /** @var array $rawSettings */
        $rawSettings = $this->callApiV1Method(
            self::API_ROUTE_PREFIX . 'list.json/getSettings',
            Setting::class
        );

        $settings = [];
        foreach ($rawSettings as $key => $value) {
            $settings[] = (new Setting())
                ->setKey($key)
                ->setValue($value);
        }

        return $settings;
    }

    /** @return Brand[] */
    public function getBrands(): array
    {
        /** @var array $rawBrands */
        $rawBrands = $this->callApiV1Method(
            self::API_ROUTE_PREFIX . 'list.json/getBrands',
            Brand::class
        );

        $brands = [];
        foreach ($rawBrands as $rawBrand) {
            $brands[] = $this
                ->serializer
                ->denormalize($rawBrand, Brand::class);
        }

        return $brands;
    }

    private function getAuthParams(array $params): array
    {
        ksort($params);
        $paramsString = '';
        foreach ($params as $param) {
            if (is_scalar($param)) {
                $paramsString .= $param;
            }
        }

        return [
            'client_id'    => $this->clientId,
            'access_token' => md5($this->clientId . $this->clientSecret . $paramsString),
        ];
    }

    private function callApiV1Method(
        string $urlSuffix,
        string $className,
        array $params = [],
        string $method = 'GET'
    ) {
        $params = http_build_query($params + $this->getAuthParams($params));
        $ch     = curl_init();
        $url    = $this->url . $urlSuffix;

        switch ($method) {
            case 'GET':
                if ($params) {
                    $url .= '?' . $params;
                }
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                break;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error || 200 != $code) {
            throw new ApiException(
                'Curl returned error' . $error . ' code: ' . $code . ' params ' . $params,
                $code,
                $error
            );
        }

//        $rawDecodeClasses = [
//            'Setting',
//            Product::class,
//            self::ORDER,
//        ];
//
//        if (array_search($className, $rawDecodeClasses) === false) {
//            return $this->serializer->deserialize($response, 'array<' . $className. '>', 'json');
//        }

//        return $this->serializer->deserialize($response, 'array<' . $className. '>', 'json');

        return json_decode($response, true);
    }
}
