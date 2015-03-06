<?php

defined ('_JEXEC') or die('Restricted access');
if (!class_exists ('vmPSPlugin')) {
	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class plgVmpaymentPayeer extends vmPSPlugin
{
    public static $_this = false;
	
    function __construct(& $subject, $config)
    {
        parent::__construct($subject, $config);
		$jlang = JFactory::getLanguage ();
		$jlang->load ('plg_vmpayment_payeer', JPATH_ADMINISTRATOR, NULL, TRUE);
        $this->_loggable   = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $varsToPush        = array(
            'payment_logos' 	=> array('', 'char'),
            'countries' 		=> array(0, 'int'),
            'payment_currency'	=> array(0, 'int'),
			'merchant_url' 		=> array('//payeer.com/merchant/', 'string'),
            'merchant_id' 		=> array('', 'string'),
            'secret_key' 		=> array('', 'string'),
            'status_success' 	=> array('', 'char'),
            'status_pending' 	=> array('', 'char'),
            'status_canceled' 	=> array('', 'char'),
			'order_desc' 		=> array('', 'string'),
			'ip_filter' 		=> array('', 'string'),
			'admin_email' 		=> array('', 'string'),
			'log_file' 			=> array('', 'string')
        );
        
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }
    
    protected function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment Payeer Table');
    }
    
    function getTableSQLFields()
    {
        $SQLfields = array(
            'id' => 'tinyint(1) unsigned NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(11) UNSIGNED DEFAULT NULL',
            'order_number' => 'char(32) DEFAULT NULL',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED DEFAULT NULL',
            'payment_name' => 'char(255) NOT NULL DEFAULT \'\' ',
            'payment_order_total' => 'decimal(15,2) NOT NULL DEFAULT \'0.00\' ',
            'payment_currency' => 'char(3) '
        );
        
        return $SQLfields;
    }
	
    
	public function plgVmOnPaymentNotification()
    {
		if (!class_exists ('VirtueMartModelOrders')) 
		{
			require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
		}

		$mb_data = vRequest::getPost();
		
		if (isset($mb_data['m_operation_id']) && isset($mb_data['m_sign']))
		{
			$payment = $this->getDataByOrderId($mb_data['m_orderid']);
			$order_number = $payment->order_number;
			$method = $this->getVmPluginMethod($payment->virtuemart_paymentmethod_id);
			$status = $method->status_success;
			$m_key = $method->secret_key;
			$arHash = array(
				$mb_data['m_operation_id'],
				$mb_data['m_operation_ps'],
				$mb_data['m_operation_date'],
				$mb_data['m_operation_pay_date'],
				$mb_data['m_shop'],
				$mb_data['m_orderid'],
				$mb_data['m_amount'],
				$mb_data['m_curr'],
				$mb_data['m_desc'],
				$mb_data['m_status'],
				$m_key
			);
			
			$sign_hash = strtoupper(hash('sha256', implode(":", $arHash)));
			
			$list_ip_str = str_replace(' ', '', $method->ip_filter);
			
			if ($list_ip_str != '') 
			{
				$list_ip = explode(',', $list_ip_str);
				$this_ip = $_SERVER['REMOTE_ADDR'];
				$this_ip_field = explode('.', $this_ip);
				$list_ip_field = array();
				$i = 0;
				$valid_ip = FALSE;
				foreach ($list_ip as $ip)
				{
					$ip_field[$i] = explode('.', $ip);
					if ((($this_ip_field[0] ==  $ip_field[$i][0]) || ($ip_field[$i][0] == '*')) &&
						(($this_ip_field[1] ==  $ip_field[$i][1]) || ($ip_field[$i][1] == '*')) &&
						(($this_ip_field[2] ==  $ip_field[$i][2]) || ($ip_field[$i][2] == '*')) &&
						(($this_ip_field[3] ==  $ip_field[$i][3]) || ($ip_field[$i][3] == '*')))
						{
							$valid_ip = TRUE;
							break;
						}
					$i++;
				}
			}
			else
			{
				$valid_ip = TRUE;
			}
			
			$path_to_logfile = $method->log_file;
			
			$log_text = 
				"--------------------------------------------------------\n".
				"operation id		".$mb_data["m_operation_id"]."\n".
				"operation ps		".$mb_data["m_operation_ps"]."\n".
				"operation date		".$mb_data["m_operation_date"]."\n".
				"operation pay date	".$mb_data["m_operation_pay_date"]."\n".
				"shop				".$mb_data["m_shop"]."\n".
				"order id			".$mb_data["m_orderid"]."\n".
				"amount				".$mb_data["m_amount"]."\n".
				"currency			".$mb_data["m_curr"]."\n".
				"description		".base64_decode($mb_data["m_desc"])."\n".
				"status				".$mb_data["m_status"]."\n".
				"sign				".$mb_data["m_sign"]."\n\n";
						
			if (!empty($path_to_logfile))
			{	
				file_put_contents($_SERVER['DOCUMENT_ROOT'] . $path_to_logfile, $log_text, FILE_APPEND);
			}
			
			if ($mb_data['m_sign'] == $sign_hash && $mb_data['m_status'] == "success" && $valid_ip)
			{
				$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
				
				$order['order_status']        	= $status;
				$order['virtuemart_order_id'] 	= $payment->virtuemart_order_id;
				$order['virtuemart_user_id'] 	= $payment->virtuemart_user_id;
				$order['order_total'] 			= $mb_data['m_amount'];
				$order['customer_notified']   	= 0;
				$order['virtuemart_vendor_id']	= 1;
				$order['comments']            	= vmText::sprintf('VMPAYMENT_PAYEER_PAYMENT_CONFIRMED', $order_number);
				
				$modelOrder = new VirtueMartModelOrders();
				
				$modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, TRUE);
				
				exit($mb_data['m_orderid'] . '|success');
			}
			else
			{
				$to = $method->admin_email;
				$subject = "Error payment";
				$message = "Failed to make the payment through the system Payeer for the following reasons:\n\n";
				
				if ($mb_data["m_sign"] != $sign_hash)
				{
					$message .= " - Do not match the digital signature\n";
				}
				
				if ($mb_data['m_status'] != "success")
				{
					$message .= " The payment status is not success\n";
				}
				
				if (!$valid_ip)
				{
					$message .= " - the ip address of the server is not trusted\n";
					$message .= "   trusted ip: " . $method->ip_filter . "\n";
					$message .= "   ip of the current server: " . $_SERVER['REMOTE_ADDR'] . "\n";
				}
				
				$message .= "\n" . $log_text;
				
				$headers = "From: no-reply@" . $_SERVER['HTTP_SERVER'] . "\r\nContent-type: text/plain; charset=utf-8 \r\n";
				mail($to, $subject, $message, $headers);
			}

			exit ($mb_data['m_orderid'] . '|error');
		}
    }
	
	function plgVmOnPaymentResponseReceived (&$html) 
	{
		if (!class_exists ('VirtueMartCart')) {
			require(VMPATH_SITE . DS . 'helpers' . DS . 'cart.php');
		}
		if (!class_exists ('shopFunctionsF')) {
			require(VMPATH_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
		}
		if (!class_exists ('VirtueMartModelOrders')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
		}

		VmConfig::loadJLang('com_virtuemart_orders', TRUE);
		$mb_data = vRequest::getPost();


		// the payment itself should send the parameter needed.
		$virtuemart_paymentmethod_id = vRequest::getInt ('pm', 0);
		$order_number = vRequest::getString ('on', 0);
		if (!($method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id))) {
			return NULL;
		} // Another method was selected, do nothing

		if (!$this->selectedThisElement ($method->payment_element)) {
			return NULL;
		}

		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber ($order_number))) {
			return NULL;
		}

		if (!($paymentTable = $this->getDataByOrderId ($virtuemart_order_id))) {
			// JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}
		VmConfig::loadJLang('com_virtuemart');
		$orderModel = VmModel::getModel('orders');
		$order = $orderModel->getOrder($virtuemart_order_id);

		vmdebug ('Payeer plgVmOnPaymentResponseReceived', $mb_data);
		$payment_name = $this->renderPluginName ($method);
		$html = $this->_getPaymentResponseHtml ($paymentTable, $payment_name);
		$link=	JRoute::_("index.php?option=com_virtuemart&view=orders&layout=details&order_number=".$order['details']['BT']->order_number."&order_pass=".$order['details']['BT']->order_pass, false) ;

		$html .='<br />
		<a class="vm-button-correct" href="'.$link.'">'.vmText::_('COM_VIRTUEMART_ORDER_VIEW_ORDER').'</a>';

		$cart = VirtueMartCart::getCart ();
		$cart->emptyCart ();
		return TRUE;
	}
	
	function plgVmOnUserPaymentCancel () 
	{
		if (!class_exists ('VirtueMartModelOrders')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
		}

		$order_number = vRequest::getString ('on', '');
		$virtuemart_paymentmethod_id = vRequest::getInt ('pm', '');
		if (empty($order_number) or
			empty($virtuemart_paymentmethod_id) or
			!$this->selectedThisByMethodId ($virtuemart_paymentmethod_id)
		) {
			return NULL;
		}

		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber ($order_number))) {
			return NULL;
		}

		if (!($paymentTable = $this->getDataByOrderId ($virtuemart_order_id))) {
			return NULL;
		}

		VmInfo (vmText::_ ('VMPAYMENT_SKRILL_PAYMENT_CANCELLED'));
		$session = JFactory::getSession ();
		$return_context = $session->getId ();
		if (strcmp ($paymentTable->user_session, $return_context) === 0) {
			$this->handlePaymentUserCancel ($virtuemart_order_id);
		}

		return TRUE;
	}

    function plgVmConfirmedOrder($cart, $order)
    {
		if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id)))
		{
			return null;
		}
		
		if (!$this->selectedThisElement($method->payment_element)) 
		{
			return false;
        }
		
        $lang     = JFactory::getLanguage();
        $filename = 'com_virtuemart';
        $lang->load($filename, JPATH_ADMINISTRATOR);
        $session        = JFactory::getSession();
        $return_context = $session->getId();
        $this->logInfo('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');
        if (!class_exists('VirtueMartModelOrders'))
		{
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}
		
        if (!$method->payment_currency)
		{
            $this->getPaymentCurrency($method);
		}

        $q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
        $db =& JFactory::getDBO();
        $db->setQuery($q);
		
		$m_url = $method->merchant_url;
		
        $currency = strtoupper($db->loadResult());
		
        if ($currency == 'RUR')
		{
			$currency = 'RUB';
		}
		
		$amount = number_format($order['details']['BT']->order_total, 2, '.', '');
        $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order['details']['BT']->order_number);
        $desc = base64_encode($method->order_desc);

		$m_key = $method->secret_key;
		$arHash = array(
			$method->merchant_id,
			$virtuemart_order_id,
			$amount,
			$currency,
			$desc,
			$m_key
		);
		$sign = strtoupper(hash('sha256', implode(":", $arHash)));

        $this->_virtuemart_paymentmethod_id      = $order['details']['BT']->virtuemart_paymentmethod_id;
        $dbValues['payment_name']                = $this->renderPluginName($method);
        $dbValues['order_number']                = $order['details']['BT']->order_number;
        $dbValues['virtuemart_paymentmethod_id'] = $this->_virtuemart_paymentmethod_id;
        $dbValues['payment_currency']            = $currency;
        $dbValues['payment_order_total']         = $amount;
        $this->storePSPluginInternalData($dbValues);
       
		$html = '';
		$html .= '<form method="GET" name="vm_payeer_form" action="' . $m_url . '">';
		$html .= '<input type="hidden" name="m_shop" value="' . $method->merchant_id . '">';
		$html .= '<input type="hidden" name="m_orderid" value="' . $virtuemart_order_id . '">';
		$html .= '<input type="hidden" name="m_amount" value="' . $amount . '">';
		$html .= '<input type="hidden" name="m_curr" value="' . $currency . '">';
		$html .= '<input type="hidden" name="m_desc" value="' . $desc . '">';
		$html .= '<input type="hidden" name="m_sign" value="' . $sign . '">';
		$html .= '</form>';
        $html .= '<script type="text/javascript">';
        $html .= 'document.forms.vm_payeer_form.submit();';
        $html .= '</script>';
		
        return $this->processConfirmedOrderPaymentResponse(true, $cart, $order, $html, $this->renderPluginName($method, $order), 'P');
    }
    
    function plgVmOnShowOrderBEPayment ($virtuemart_order_id, $payment_method_id) {

		if (!$this->selectedThisByMethodId ($payment_method_id)) {
			return NULL;
		} // Another method was selected, do nothing

		if (!($paymentTable = $this->_getInternalData ($virtuemart_order_id))) {
			// JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}

		$this->getPaymentCurrency ($paymentTable);
		$q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' .
			$paymentTable->payment_currency . '" ';
		$db = JFactory::getDBO ();
		$db->setQuery ($q);
		$currency_code_3 = $db->loadResult ();
		$html = '<table class="adminlist table">' . "\n";
		$html .= $this->getHtmlHeaderBE ();
		$html .= $this->getHtmlRowBE ('PAYMENT_NAME', $paymentTable->payment_name);

		$code = "mb_";
		foreach ($paymentTable as $key => $value) {
			if (substr ($key, 0, strlen ($code)) == $code) {
				$html .= $this->getHtmlRowBE ($key, $value);
			}
		}
		$html .= '</table>' . "\n";
		return $html;
	}
    
    function getCosts(VirtueMartCart $cart, $method, $cart_prices)
    {
        return 0;
    }
    
    protected function checkConditions($cart, $method, $cart_prices)
    {
        return true;
    }
    
    function plgVmOnStoreInstallPaymentPluginTable ($jplugin_id) {

		return $this->onStoreInstallPluginTable ($jplugin_id);
	}
    
    public function plgVmOnSelectCheckPayment (VirtueMartCart $cart,  &$msg) {

		return $this->OnSelectCheck ($cart);
	}
    
    public function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn) {

		return $this->displayListFE ($cart, $selected, $htmlIn);
	}
    
    public function plgVmonSelectedCalculatePricePayment (VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {

		return $this->onSelectedCalculatePrice ($cart, $cart_prices, $cart_prices_name);
	}
    
    function plgVmgetPaymentCurrency ($virtuemart_paymentmethod_id, &$paymentCurrencyId) 
	{
		if (!($method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id))) 
		{
			return NULL;
		}

		if (!$this->selectedThisElement ($method->payment_element)) 
		{
			return FALSE;
		}

		$this->getPaymentCurrency ($method);
		$paymentCurrencyId = $method->payment_currency;
	}
	
    function plgVmOnCheckAutomaticSelectedPayment (VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {

		return $this->onCheckAutomaticSelected ($cart, $cart_prices, $paymentCounter);
	}
    
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }
    
    function plgVmonShowOrderPrintPayment($order_number, $method_id)
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }
    
    function plgVmDeclarePluginParamsPayment($name, $id, &$data)
    {
        return $this->declarePluginParams('payment', $name, $id, $data);
    }
    
    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }
    
	function plgVmDeclarePluginParamsPaymentVM3( &$data) {
		return $this->declarePluginParams('payment', $data);
	}
	
    protected function displayLogos($logo_list)
    {
        $img = "";
        
        if (!(empty($logo_list))) 
		{
            $url = JURI::root() . str_replace('\\', '/', str_replace(JPATH_ROOT, '', dirname(__FILE__))) . '/';
            if (!is_array($logo_list))
                $logo_list = (array) $logo_list;
            foreach ($logo_list as $logo) 
			{
                $alt_text = substr($logo, 0, strpos($logo, '.'));
                $img .= '<img align="middle" src="' . $url . $logo . '"  alt="' . $alt_text . '" /> ';
            }
        }
        return $img;
    }

    private function notifyCustomer($order, $order_info)
    {
        $lang     = JFactory::getLanguage();
        $filename = 'com_virtuemart';
        $lang->load($filename, JPATH_ADMINISTRATOR);
        if (!class_exists('VirtueMartControllerVirtuemart'))
            require(JPATH_VM_SITE . DS . 'controllers' . DS . 'virtuemart.php');
        
        if (!class_exists('shopFunctionsF'))
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
        $controller = new VirtueMartControllerVirtuemart();
        $controller->addViewPath(JPATH_VM_ADMINISTRATOR . DS . 'views');
        
        $view = $controller->getView('orders', 'html');
        if (!$controllerName)
            $controllerName = 'orders';
        $controllerClassName = 'VirtueMartController' . ucfirst($controllerName);
        if (!class_exists($controllerClassName))
            require(JPATH_VM_SITE . DS . 'controllers' . DS . $controllerName . '.php');
        
        $view->addTemplatePath(JPATH_COMPONENT_ADMINISTRATOR . '/views/orders/tmpl');
        
        $db = JFactory::getDBO();
        $q  = "SELECT CONCAT_WS(' ',first_name, middle_name , last_name) AS full_name, email, order_status_name
			FROM #__virtuemart_order_userinfos
			LEFT JOIN #__virtuemart_orders
			ON #__virtuemart_orders.virtuemart_user_id = #__virtuemart_order_userinfos.virtuemart_user_id
			LEFT JOIN #__virtuemart_orderstates
			ON #__virtuemart_orderstates.order_status_code = #__virtuemart_orders.order_status
			WHERE #__virtuemart_orders.virtuemart_order_id = '" . $order['virtuemart_order_id'] . "'
			AND #__virtuemart_orders.virtuemart_order_id = #__virtuemart_order_userinfos.virtuemart_order_id";
        $db->setQuery($q);
        $db->query();
        $view->user  = $db->loadObject();
        $view->order = $order;
        JRequest::setVar('view', 'orders');
        $user = $this->sendVmMail($view, $order_info['details']['BT']->email, false);
        if (isset($view->doVendor)) {
            $this->sendVmMail($view, $view->vendorEmail, true);
        }
    }

    private function sendVmMail(&$view, $recipient, $vendor = false)
    {
        ob_start();
        $view->renderMailLayout($vendor, $recipient);
        $body = ob_get_contents();
        ob_end_clean();
        
        $subject = (isset($view->subject)) ? $view->subject : JText::_('COM_VIRTUEMART_DEFAULT_MESSAGE_SUBJECT');
        $mailer  = JFactory::getMailer();
        $mailer->addRecipient($recipient);
        $mailer->setSubject($subject);
        $mailer->isHTML(VmConfig::get('order_mail_html', true));
        $mailer->setBody($body);
        
        if (!$vendor) 
		{
            $replyto[0] = $view->vendorEmail;
            $replyto[1] = $view->vendor->vendor_name;
            $mailer->addReplyTo($replyto);
        }
        
        if (isset($view->mediaToSend)) 
		{
            foreach ((array) $view->mediaToSend as $media) 
			{
                $mailer->addAttachment($media);
            }
        }
        return $mailer->Send();
    }
    
}
