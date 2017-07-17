<?php
/**
 * Export magento attributes and attribute sets
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
$entityTypeId = Mage::getModel('catalog/product')->getResource()->getTypeId();
prepareCollection($entityTypeId);

function prepareCollection($entTypeId) {
	$resource = Mage::getSingleton('core/resource');
	$connection = $resource->getConnection('core_read');
	$selectAttrs = $connection->select()
		->from(array('ea' => $resource->getTableName('eav/attribute')))
		->join(array('c_ea' => $resource->getTableName('catalog/eav_attribute')), 'ea.attribute_id = c_ea.attribute_id');
		// ->join(array('e_ao'=>$resource->getTableName('eav/attribute_option'), array('option_id')), 'c_ea.attribute_id = e_ao.attribute_id')
		// ->join(array('e_aov'=>$resource->getTableName('eav/attribute_option_value'), array('value')), 'e_ao.option_id = e_aov.option_id and store_id = 0')
	$selectProdAttrs = $selectAttrs->where('ea.entity_type_id = ' . $entTypeId)->order('ea.attribute_id ASC');
	$productAttributes = $connection->fetchAll($selectProdAttrs);
	$selectAttrOption = $selectAttrs->join(array(
			'e_ao' => $resource->getTableName('eav/attribute_option'),
			array('option_id')
		), 'c_ea.attribute_id = e_ao.attribute_id')
		->join(array(
			'e_aov' => $resource->getTableName('eav/attribute_option_value'),
			array('value')
		), 'e_ao.option_id = e_aov.option_id and store_id = 0')
		->order('e_ao.attribute_id ASC');
	$productAttributeOptions = $connection->fetchAll($selectAttrOption);
	$attributesCollection = mergeCollections($productAttributes, $productAttributeOptions);
	prepareCsv($attributesCollection);
}

function mergeCollections($productAttributes, $productAttributeOptions) {
	foreach ($productAttributes as $key => $_prodAttr) {
		$values = array();
		$attrId = $_prodAttr['attribute_id'];
		foreach ($productAttributeOptions as $pao) {
			if ($pao['attribute_id'] == $attrId) {
				$values[] = $pao['value'];
			}
		}
		if (count($values) > 0) {
			$values = implode(';', $values);
			$productAttributes[$key]['_options'] = $values;
		} else {
			$productAttributes[$key]['_options'] = '';
		}
		/*
			temp
		*/
		$productAttributes[$key]['attribute_code'] = $productAttributes[$key]['attribute_code'];
	}

	return $productAttributes;

}

function prepareCsv($attributesCollection, $filename = 'importAttrs.csv', $delimiter = '|', $enclosure = '"') {
	$f = fopen('php://memory', 'w');
	$first = true;
	foreach ($attributesCollection as $line) {
		if ($first) {
			$titles = array();
			foreach ($line as $field => $val) {
				$titles[] = $field;
			}
			fputcsv($f, $titles, $delimiter, $enclosure);
			$first = false;
		}
		fputcsv($f, $line, $delimiter, $enclosure);
	}
	fseek($f, 0);
	header('Content-Type: application/csv');
	header('Content-Disposition: attachement; filename="' . $filename . '"');
	fpassthru($f);
}
