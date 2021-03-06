<?php
 
class Hackathon_Layeredlanding_Adminhtml_LayeredlandingController extends Mage_Adminhtml_Controller_Action
{

	protected function _initAction()
	{
		$this->loadLayout()
            ->_setActiveMenu('system/tools')
            ->_addBreadcrumb($this->__('Catalog'), $this->__('Catalog'))
            ->_addBreadcrumb($this->__('Attributes'), $this->__('Attributes'))
			->_addBreadcrumb(Mage::helper('layeredlanding')->__('Attribute Landing Pages'), Mage::helper('layeredlanding')->__('Attribute Landing Pages'));

        $this->_title($this->__('Catalog'))
             ->_title($this->__('Attributes'))
             ->_title(Mage::helper('layeredlanding')->__('Attribute Landing Pages'));

		return $this;
	}



    /**
     * Categories tree node (Ajax version)
     */
    public function categoriesJsonAction()
    {
        if ($categoryId = (int) $this->getRequest()->getPost('id')) {
            /** @var Hackathon_Layeredlanding_Block_Adminhtml_Layeredlanding_Edit_Renderer_Categories $treeBlock */
            $treeBlock = $this->getLayout()->createBlock('layeredlanding/adminhtml_layeredlanding_edit_renderer_categories');

            $layeredlandingId = $this->getRequest()->getParam('layeredlanding_id');
            $layeredlanding	= Mage::getModel('layeredlanding/layeredlanding')->load($layeredlandingId);
            $treeBlock->setCategoryIds($layeredlanding->getCategoryId());


            $this->getResponse()->setBody($treeBlock->getCategoryChildrenJson($categoryId));
        }
    }
   
	public function indexAction() {
		$this->_initAction();       
		$this->_addContent($this->getLayout()->createBlock('layeredlanding/adminhtml_layeredlanding'));
		$this->renderLayout();
	}
 
	public function editAction()
	{
		$layeredlandingId		= $this->getRequest()->getParam('id');
		$layeredlandingModel	= Mage::getModel('layeredlanding/layeredlanding')->load($layeredlandingId);

		if ($layeredlandingModel->getId() || $layeredlandingId == 0) {
			Mage::register('layeredlanding_data', $layeredlandingModel);

			$this->_initAction();
            $title = Mage::helper('layeredlanding')->__("Edit Landing Page '%s'", $layeredlandingModel->getPageTitle());
            $this->_addBreadcrumb($title, $title);
            $this->_title($title);

			$this->renderLayout();
		} else {
			Mage::getSingleton('adminhtml/session')->addError(Mage::helper('layeredlanding')->__('Landing Page does not exist'));
			$this->_redirect('*/*/');
		}
	}
   
	public function newAction()
	{
		$this->_forward('edit');
	}
   
	public function saveAction()
	{
		if ( $this->getRequest()->getPost() ) 
		{
			try {
				$postData = $this->getRequest()->getPost();

                $postData['category_id'] = explode(',', $postData['category_id']);
                if (isset($postData['list_mode']) && in_array($postData['list_mode'], array('grid','list'))) {
                    $key = sprintf('limit_%s', $postData['list_mode']);
                    if (isset($postData[$key])) {
                        $postData['limit'] = $postData[$key];
                    }
                }

                /** @var Hackathon_Layeredlanding_Model_Layeredlanding $model */
				$model = Mage::getModel('layeredlanding/layeredlanding');
                if ($id = $this->getRequest()->getParam('id')) {
                    $model->load($id);
                    if (! $model->getId()) {
                        Mage::throwException(Mage::helper('layeredlanding')->__('Could not save Landing Page, does not exist'));
                    }
                }

                $model->addData($postData);
				$model->save();
				
				$layeredLandingId = $model->getId();
				
				// save opening hours
				if (isset($postData['attributes']))
				{
					foreach ($postData['attributes']['delete'] as $_key => $_row)
					{
						$delete = (int)$_row;
						$object_data = $postData['attributes']['value'][$_key];
						
						$attributes_object = Mage::getModel('layeredlanding/attributes')->load((int)$object_data['id']);
						
						if ($delete && 0 < (int)$attributes_object->getId()) // exists & required to delete
						{
							$attributes_object->delete();
							continue;
						}

                        // Check if the attribute and value values are set
                        $canSave = true;
                        if (0 == (int)$attributes_object->getId() && (empty($object_data['value']) || empty($object_data['attribute']))) // new item but no values
                        {
                            $canSave = false;
                        }
                        elseif (0 < (int)$attributes_object->getId() && empty($object_data['value'])) // existing item but no value
                        {
                            $canSave = false;
                        }

						if (!$delete && $canSave) // save if not deleted and data checks out
						{
							$attributes_object->setData('layeredlanding_id', $layeredLandingId);
							$attributes_object->setData('attribute_id', $object_data['attribute']);
							$attributes_object->setData('value', $object_data['value']);
							
							$attributes_object->save();
						}
					}
				}
				
				// And wrap up the transaction
				Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('layeredlanding')->__('Landing Page was successfully saved'));
				Mage::getSingleton('adminhtml/session')->setLayeredlandingData(false);

                if ($this->getRequest()->getParam("back")) {
                    $this->_redirect("*/*/edit", array("id" => $model->getId()));
                    return;
                }
				$this->_redirect('*/*/');
				return;
			} catch (Exception $e) {
				Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
				Mage::getSingleton('adminhtml/session')->setLayeredlandingData($this->getRequest()->getPost());
				$this->_redirect('*/*/edit', array('id' => $this->getRequest()->getParam('id')));
				return;
			}
		}
		$this->_redirect('*/*/');
	}
   
	public function deleteAction()
	{
		if( $this->getRequest()->getParam('id') > 0 )
		{
			try {
				$model = Mage::getModel('layeredlanding/layeredlanding');

				$model->setId($this->getRequest()->getParam('id'))
					->delete();

				Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('layeredlanding')->__('Landing Page was successfully deleted'));
				$this->_redirect('*/*/');
			} catch (Exception $e) {
				Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
				$this->_redirect('*/*/edit', array('id' => $this->getRequest()->getParam('id')));
			}
		}
		$this->_redirect('*/*/');
	}
	
	/**
	* Product grid for AJAX request.
	* Sort and filter result for example.
	*/
	public function gridAction()
	{
		$this->loadLayout();
		$this->getResponse()->setBody(
			$this->getLayout()->createBlock('layeredlanding/adminhtml_layeredlanding_grid')->toHtml()
		);
	}

    public function ajaxValuesAction()
    {
        $request = Mage::app()->getRequest();

        $attribute_id = (int)$request->getParam('attributeid', false);
        $store_id = $request->getParam('storeid', 0);
        $input_name = $request->getParam('inputname');
		
		echo Mage::getModel('layeredlanding/attributes')->getGridOptionsHtml($attribute_id, $store_id, 0, $input_name);
    }

    public function ajaxCountResultAction()
    {
        $request = Mage::app()->getRequest()->getParams();
		
		$category = $request['category'];
		$stores = explode(',', trim($request['store'], ','));
		
		unset($request['isAjax'], $request['form_key'], $request['category'], $request['store']);
		
		foreach ($stores as $store)
		{
			$collection = Mage::getModel('catalog/product')->getCollection();
			
			// get not only this cat, but also it's childrens products
			$categories = Mage::getModel('catalog/category')->load((int)$category)->getAllChildren(true);
			$collection->joinField('category_id', 'catalog/category_product', 'category_id', 'product_id=entity_id', null, 'left');
			$collection->addAttributeToFilter('category_id', array('in' => $categories));
						
			$collection->setStoreId((int)$store);
			
			foreach ($request as $attribute_id => $value)
			{
				$attribute_code = Mage::helper('layeredlanding')->attributeIdToCode($attribute_id);
				if ($attribute_code == 'price')
				{
					$price_range = explode('-', $value);
					$collection->addFieldToFilter('price', array('from'=>$price_range[0],'to'=>$price_range[1]));
				}
				else
				{
					$collection->addAttributeToFilter($attribute_code, $value);
				}
			}
			
			Mage::getSingleton('catalog/product_status')->addVisibleFilterToCollection($collection);
			
			//var_dump((string)$collection->getSelect());exit;
			
			echo Mage::helper('layeredlanding')->__('Estimated product count for store \'%s\' is %d', Mage::app()->getStore($store)->getName(), $collection->getSize()) . "<br/>";
		}
    }

    function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('catalog/attributes/layeredlanding');
    }
}