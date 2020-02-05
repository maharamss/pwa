<?php
use Magento\Framework\App\Bootstrap;

/**
 * If the external file is in the root folder
 */
require __DIR__ . '/app/bootstrap.php';

$params = $_SERVER;

$bootstrap = Bootstrap::create(BP, $params);

$obj = $bootstrap->getObjectManager();

$state = $obj->get('Magento\Framework\App\State');
$state->setAreaCode('frontend');

$objectManager = \Magento\Framework\App\ObjectManager::getInstance();

$storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
$store = $storeManager->getStore();
$websiteId = $storeManager->getStore()->getWebsiteId();

$firstName = 'maha';
$lastName = 'rams';
$email = 'admin@gmail.com';
$password = 'admin@123';

$address = array(
    //'customer_address_id' => '',
    'prefix' => 'r',
    'firstname' => $firstName,
    'middlename' => 'r',
    'lastname' => $lastName,
    'suffix' => 'r',
    'company' => 'tychons', 
    'street' => array(
        '0' => 'Customer Address 1' // this is mandatory
        //'1' => 'Customer Address 2' // this is optional
    ),
    'city' => 'New York',
    'country_id' => 'US', // two letters country code
    'region' => 'New York', // can be empty '' if no region
    'region_id' => '43', // can be empty '' if no region_id
    'postcode' => '10450',
    'telephone' => '123-456-7890',
    'fax' => '',
    'save_in_address_book' => 1
);

$customerFactory = $objectManager->get('\Magento\Customer\Model\CustomerFactory')->create();

/**
 * check whether the email address is already registered or not
 */
$customer = $customerFactory->setWebsiteId($websiteId)->loadByEmail($email);
if (!$customer->getId()) {
    try {
        $customer = $objectManager->get('\Magento\Customer\Model\CustomerFactory')->create();
        $customer->setWebsiteId($websiteId);
        $customer->setEmail($email);
        $customer->setFirstname($firstName);
        $customer->setLastname($lastName);
        $customer->setPassword($password);
        $customer->save();

        $customer->setConfirmation(null);
        $customer->save();

        $customAddress = $objectManager->get('\Magento\Customer\Model\AddressFactory')->create();
        $customAddress->setData($address)
                      ->setCustomerId($customer->getId())
                      ->setIsDefaultBilling('1')
                      ->setIsDefaultShipping('1')
                      ->setSaveInAddressBook('1');
        $customAddress->save();  
    } catch (Exception $e) {
        echo $e->getMessage();
    }
}

$customer = $objectManager->get('\Magento\Customer\Api\CustomerRepositoryInterface')->getById($customer->getId());
try {

    $quoteFactory = $objectManager->get('\Magento\Quote\Model\QuoteFactory')->create();
    $quoteFactory->setStore($store);
    $quoteFactory->setCurrency();
    $quoteFactory->assignCustomer($customer);

    $productIds = array(477 => 4, 713 => 5);    
    foreach($productIds as $productId => $qty) {
        $product = $objectManager->get('\Magento\Catalog\Model\ProductRepository')->getById($productId);// get product by product id 
        $quoteFactory->addProduct($product, $qty);  // add products to quote
    } 

    /*
     * Set Address to quote
     */
    $quoteFactory->getBillingAddress()->addData($address);
    $quoteFactory->getShippingAddress()->addData($address);

    /*
     * Collect Rates and Set Shipping & Payment Method
     */
    $shippingAddress = $quoteFactory->getShippingAddress();
    $shippingAddress->setCollectShippingRates(true)
                    ->collectShippingRates()
                    //->setShippingMethod('flatrate_flatrate'); //shipping methodfreeshipping_freeshipping
                    ->setShippingMethod('freeshipping_freeshipping'); //shipping method

    $quoteFactory->setPaymentMethod('checkmo'); //payment method
    $quoteFactory->setInventoryProcessed(false);
    $quoteFactory->save();

    /*
     * Set Sales Order Payment
     */
    //$quoteFactory->getPayment()->importData(array('method' => 'banktransfer'));
    $quoteFactory->getPayment()->importData(['method' => 'checkmo']);

    /*
     * Collect Totals & Save Quote
     */
    $quoteFactory->collectTotals()->save();

    /*
     * Create Order From Quote
     */
    $order = $objectManager->get('\Magento\Quote\Model\QuoteManagement')->submit($quoteFactory);
    //$order->setEmailSent(0);
    //echo 'Order Id:' . $order->getRealOrderId();
} catch (Exception $e) {
    echo $e->getMessage();
}