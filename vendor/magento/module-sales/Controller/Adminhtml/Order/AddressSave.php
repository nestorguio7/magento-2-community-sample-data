<?php
/**
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Sales\Controller\Adminhtml\Order;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Directory\Model\RegionFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Controller\Adminhtml\Order;
use Magento\Sales\Model\Order\Address as ModelOrderAddress;
use Psr\Log\LoggerInterface;
use Magento\Framework\Registry;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Translate\InlineInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\Result\LayoutFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\ObjectManager;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AddressSave extends Order
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Magento_Sales::actions_edit';
    
    /**
     * @var RegionFactory
     */
    private $regionFactory;

    /**
     * @param Context $context
     * @param Registry $coreRegistry
     * @param FileFactory $fileFactory
     * @param InlineInterface $translateInline
     * @param PageFactory $resultPageFactory
     * @param JsonFactory $resultJsonFactory
     * @param LayoutFactory $resultLayoutFactory
     * @param RawFactory $resultRawFactory
     * @param OrderManagementInterface $orderManagement
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     * @param RegionFactory|null $regionFactory
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        FileFactory $fileFactory,
        InlineInterface $translateInline,
        PageFactory $resultPageFactory,
        JsonFactory $resultJsonFactory,
        LayoutFactory $resultLayoutFactory,
        RawFactory $resultRawFactory,
        OrderManagementInterface $orderManagement,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger,
        RegionFactory $regionFactory = null
    ) {
        $this->regionFactory = $regionFactory ?: ObjectManager::getInstance()->get(RegionFactory::class);
        parent::__construct(
            $context,
            $coreRegistry,
            $fileFactory,
            $translateInline,
            $resultPageFactory,
            $resultJsonFactory,
            $resultLayoutFactory,
            $resultRawFactory,
            $orderManagement,
            $orderRepository,
            $logger
        );
    }

    /**
     * Save order address
     *
     * @return Redirect
     */
    public function execute()
    {
        $addressId = $this->getRequest()->getParam('address_id');
        /** @var $address OrderAddressInterface|ModelOrderAddress */
        $address = $this->_objectManager->create(
            OrderAddressInterface::class
        )->load($addressId);
        $data = $this->getRequest()->getPostValue();
        $data = $this->updateRegionData($data);
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($data && $address->getId()) {
            $address->addData($data);
            try {
                $address->save();
                $this->_eventManager->dispatch(
                    'admin_sales_order_address_update',
                    [
                        'order_id' => $address->getParentId()
                    ]
                );
                $this->messageManager->addSuccess(__('You updated the order address.'));

                return $resultRedirect->setPath('sales/*/view', ['order_id' => $address->getParentId()]);
            } catch (LocalizedException $e) {
                $this->messageManager->addError($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addException($e, __('We can\'t update the order address right now.'));
            }

            return $resultRedirect->setPath('sales/*/address', ['address_id' => $address->getId()]);
        } else {
            return $resultRedirect->setPath('sales/*/');
        }
    }

    /**
     * Update region data
     *
     * @param array $attributeValues
     * @return array
     */
    private function updateRegionData($attributeValues)
    {
        if (!empty($attributeValues['region_id'])) {
            $newRegion = $this->regionFactory->create()->load($attributeValues['region_id']);
            $attributeValues['region_code'] = $newRegion->getCode();
            $attributeValues['region'] = $newRegion->getDefaultName();
        }

        return $attributeValues;
    }
}
