<?php
/**
 * Import magento attributes and attribute sets
 *
 * Run from Web
 *
 * @category Agere
 * @package Agere_Shell
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 18.08.2015 17:59
 */
/**
 * Thanks @Shathish on stackoverflow for this script
 *
 * @see http://magento.stackexchange.com/a/11563
 */
define('MAGENTO', realpath(dirname(__DIR__)));
require_once MAGENTO . '/app/Mage.php';
Mage::app();
$fileName = MAGENTO . '/var/import/importAttrs.csv';
//$fileName = 'importAttrib.csv';
// getCsv($fileName);
getAttributeCsv($fileName);

function getAttributeCsv($fileName) {
	// $csv = array_map("str_getcsv", file($fileName,FILE_SKIP_EMPTY_LINES));
	$file = fopen($fileName, "r");
	$delimiter = '|';
	while (!feof($file)) {
		$csv[] = fgetcsv($file, 0, $delimiter);
	}
	$keys = array_shift($csv);
	foreach ($csv as $i => $row) {
		$csv[$i] = array_combine($keys, $row);
	}
	foreach ($csv as $row) {
		$labelText = $row['frontend_label'];
		$attributeCode = $row['attribute_code'];
		if ($row['_options'] != "") {
			$options = explode(";", $row['_options']);
		} // add this to createAttribute parameters and call "addAttributeValue" function.
		else {
			$options = -1;
		}
		if ($row['apply_to'] != "") {
			$productTypes = explode(",", $row['apply_to']);
		} else {
			$productTypes = -1;
		}
		unset($row['frontend_label'], $row['attribute_code'], $row['_options'], $row['apply_to'], $row['attribute_id'], $row['entity_type_id'], $row['search_weight']);
		createAttribute($labelText, $attributeCode, $row, $productTypes, -1, $options);
	}
}

/**
 * Create an attribute.
 *
 * For reference, see Mage_Adminhtml_Catalog_Product_AttributeController::saveAction().
 *
 * @return int|false
 */
function createAttribute($labelText, $attributeCode, $values = -1, $productTypes = -1, $setInfo = -1, $options = -1) {
	$labelText = trim($labelText);
	$attributeCode = trim($attributeCode);
	if ($labelText == '' || $attributeCode == '') {
		echo "Can't import the attribute with an empty label or code.  LABEL= [$labelText]  CODE= [$attributeCode]" . "<br/>";

		return false;
	}
	if ($values === -1) {
		$values = array();
	}
	if ($productTypes === -1) {
		$productTypes = array();
	}
	if ($setInfo !== -1 && (isset($setInfo['SetID']) == false || isset($setInfo['GroupID']) == false)) {
		echo "Please provide both the set-ID and the group-ID of the attribute-set if you'd like to subscribe to one." . "<br/>";

		return false;
	}
	echo "Creating attribute [$labelText] with code [$attributeCode]." . "<br/>";
	//>>>> Build the data structure that will define the attribute. See
	// Mage_Adminhtml_Catalog_Product_AttributeController::saveAction().
	$data = array(
		'is_global'                     => '0',
		'frontend_input'                => 'text',
		'default_value_text'            => '',
		'default_value_yesno'           => '0',
		'default_value_date'            => '',
		'default_value_textarea'        => '',
		'is_unique'                     => '0',
		'is_required'                   => '0',
		'frontend_class'                => '',
		'is_searchable'                 => '1',
		'is_visible_in_advanced_search' => '1',
		'is_comparable'                 => '1',
		'is_used_for_promo_rules'       => '0',
		'is_html_allowed_on_front'      => '1',
		'is_visible_on_front'           => '0',
		'used_in_product_listing'       => '0',
		'used_for_sort_by'              => '0',
		'is_configurable'               => '0',
		'is_filterable'                 => '0',
		'is_filterable_in_search'       => '0',
		'backend_type'                  => 'varchar',
		'default_value'                 => '',
		'is_user_defined'               => '0',
		'is_visible'                    => '1',
		'is_used_for_price_rules'       => '0',
		'position'                      => '0',
		'is_wysiwyg_enabled'            => '0',
		'backend_model'                 => '',
		'attribute_model'               => '',
		'backend_table'                 => '',
		'frontend_model'                => '',
		'source_model'                  => '',
		'note'                          => '',
		'frontend_input_renderer'       => '',
	);
	// Now, overlay the incoming values on to the defaults.
	foreach ($values as $key => $newValue) {
		if (isset($data[$key]) == false) {
			echo "Attribute feature [$key] is not valid." . "<br/>";

			return false;
		} else {
			$data[$key] = $newValue;
		}
	}
	// Valid product types: simple, grouped, configurable, virtual, bundle, downloadable, giftcard
	$data['apply_to'] = $productTypes;
	$data['attribute_code'] = $attributeCode;
	$data['frontend_label'] = array(
		0 => $labelText,
		1 => '',
		3 => '',
		2 => '',
		4 => '',
	);
	//<<<<
	//>>>> Build the model.
	$model = Mage::getModel('catalog/resource_eav_attribute');
	$model->addData($data);
	if ($setInfo !== -1) {
		$model->setAttributeSetId($setInfo['SetID']);
		$model->setAttributeGroupId($setInfo['GroupID']);
	}
	$entityTypeID = Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();
	$model->setEntityTypeId($entityTypeID);
	$model->setIsUserDefined(1);
	//<<<<
	// Save.
	try {
		$model->save();
	} catch (Exception $ex) {
		echo "Attribute [{$labelText}] could not be saved: {$ex->getMessage()}<br/>";

		return false;
	}
	if (is_array($options)) {
		foreach ($options as $_opt) {
			addAttributeValue($attributeCode, $_opt);
		}
	}
	$id = $model->getId();
	echo "Attribute [{$labelText}] has been saved as ID ({$id}).<br/>";

	// return $id;
}

function addAttributeValue($argAttribute, $argValue) {
	$attributeModel = Mage::getModel('eav/entity_attribute');
	$attributeCode = $attributeModel->getIdByCode('catalog_product', $argAttribute);
	$attribute = $attributeModel->load($attributeCode);
	if (!attributeValueExists($argAttribute, $argValue)) {
		$value['option'] = array($argValue, $argValue);
		$result = array('value' => $value);
		$attribute->setData('option', $result);
		$attribute->save();
	}
	$attributeOptionsModel = Mage::getModel('eav/entity_attribute_source_table');
	$attributeTable = $attributeOptionsModel->setAttribute($attribute);
	$options = $attributeOptionsModel->getAllOptions(false);
	foreach ($options as $option) {
		if ($option['label'] == $argValue) {
			return $option['value'];
		}
	}

	return false;
}

function attributeValueExists($argAttribute, $arg_value) {
	$attributeModel = Mage::getModel('eav/entity_attribute');
	$attributeOptionsModel = Mage::getModel('eav/entity_attribute_source_table');
	$attribute_code = $attributeModel->getIdByCode('catalog_product', $argAttribute);
	$attribute = $attributeModel->load($attribute_code);
	$attributeTable = $attributeOptionsModel->setAttribute($attribute);
	$options = $attributeOptionsModel->getAllOptions(false);
	foreach ($options as $option) {
		if ($option['label'] == $arg_value) {
			return $option['value'];
		}
	}

	return false;
}
