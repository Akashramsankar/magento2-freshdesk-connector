<?php

namespace Freshworks\MagentoConnector\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;

class GenerateTokenButton extends Field
{
    protected $_template = 'Freshworks_MagentoConnector::system/config/generate_token_button.phtml';

    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    public function getAjaxUrl(): string
    {
        return $this->getUrl('freshworks_connector/token/generate');
    }

    public function getTokenFieldId(): string
    {
        return 'freshworks_connector_events_api_token';
    }
}
