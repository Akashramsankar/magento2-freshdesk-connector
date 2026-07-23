<?php

namespace Freshworks\MagentoConnector\Controller\Adminhtml\Token;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Math\Random;

class Generate extends Action
{
    public const ADMIN_RESOURCE = 'Freshworks_MagentoConnector::token';

    private const XML_PATH_API_TOKEN = 'freshworks_connector/events/api_token';

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var Random
     */
    private $random;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Random $random,
        WriterInterface $configWriter,
        EncryptorInterface $encryptor
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->random = $random;
        $this->configWriter = $configWriter;
        $this->encryptor = $encryptor;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        try {
            $token = $this->random->getRandomString(48);
            $this->configWriter->save(self::XML_PATH_API_TOKEN, $this->encryptor->encrypt($token));
            return $result->setData([
                'success' => true,
                'token' => $token,
            ]);
        } catch (\Throwable $exception) {
            return $result->setData([
                'success' => false,
                'message' => __('Unable to generate token.')->render(),
            ]);
        }
    }
}
