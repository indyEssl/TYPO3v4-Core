<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010 Xavier Perseguers <typo3@perseguers.ch>
 *  (c) 2010 Steffen Kamper <steffen@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Contains SWFOBJECT class object.
 *
 * $Id: class.tslib_content.php 7905 2010-06-13 14:42:33Z ohader $
 * @author Xavier Perseguers <typo3@perseguers.ch>
 * @author Steffen Kamper <steffen@typo3.org>
 */
class tslib_content_ShockwaveFlashObject extends tslib_content_Abstract {

	/**
	 * Rendering the cObject, SWFOBJECT
	 *
	 * @param	array		Array of TypoScript properties
	 * @return	string		Output
	 */
	public function render($conf = array()) {
		$prefix = '';
		if ($GLOBALS['TSFE']->baseUrl) {
			$prefix = $GLOBALS['TSFE']->baseUrl;
		}
		if ($GLOBALS['TSFE']->absRefPrefix) {
			$prefix = $GLOBALS['TSFE']->absRefPrefix;
		}
		;

		$typeConf = $conf[$conf['type'] . '.'];

			//add SWFobject js-file
		$GLOBALS['TSFE']->getPageRenderer()->addJsFile(TYPO3_mainDir . 'contrib/flashmedia/swfobject/swfobject.js');

		$player = $this->cObj->stdWrap($conf[$conf['type'] . '.']['player'], $conf[$conf['type'] . '.']['player.']);
		$installUrl = $conf['installUrl'] ? $conf['installUrl'] : $prefix . TYPO3_mainDir . 'contrib/flashmedia/swfobject/expressInstall.swf';
		$filename = $this->cObj->stdWrap($conf['file'], $conf['file.']);
		if ($filename && $conf['forcePlayer']) {
			if (strpos($filename, '://') !== FALSE) {
				$conf['flashvars.']['file'] = $filename;
			} else {
				if ($prefix) {
					$conf['flashvars.']['file'] = $prefix . $filename;
				} else {
					$conf['flashvars.']['file'] = str_repeat('../', substr_count($player, '/')) . $filename;
				}

			}
		} else {
			$player = $filename;
		}
			// Write calculated values in conf for the hook
		$conf['player'] = $player;
		$conf['installUrl'] = $installUrl;
		$conf['filename'] = $filename;
		$conf['prefix'] = $prefix;

			// merge with default parameters
		$conf['flashvars.'] = array_merge((array) $typeConf['default.']['flashvars.'], (array) $conf['flashvars.']);
		$conf['params.'] = array_merge((array) $typeConf['default.']['params.'], (array) $conf['params.']);
		$conf['attributes.'] = array_merge((array) $typeConf['default.']['attributes.'], (array) $conf['attributes.']);
		$conf['embedParams'] = 'flashvars, params, attributes';

			// Hook for manipulating the conf array, it's needed for some players like flowplayer
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/hooks/class.tx_cms_mediaitems.php']['swfParamTransform'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/hooks/class.tx_cms_mediaitems.php']['swfParamTransform'] as $classRef) {
				t3lib_div::callUserFunction($classRef, $conf, $this);
			}
		}
		if (is_array($conf['flashvars.'])) {
			t3lib_div::remapArrayKeys($conf['flashvars.'], $typeConf['mapping.']['flashvars.']);
		}
		$flashvars = 'var flashvars = ' . (count($conf['flashvars.']) ? json_encode($conf['flashvars.']) : '{}') . ';';

		if (is_array($conf['params.'])) {
			t3lib_div::remapArrayKeys($conf['params.'], $typeConf['mapping.']['params.']);
		}
		$params = 'var params = ' . (count($conf['params.']) ? json_encode($conf['params.']) : '{}') . ';';

		if (is_array($conf['attributes.'])) {
			t3lib_div::remapArrayKeys($conf['attributes.'], $typeConf['attributes.']['params.']);
		}
		$attributes = 'var attributes = ' . (count($conf['attributes.']) ? json_encode($conf['attributes.']) : '{}') . ';';

		$flashVersion = $this->cObj->stdWrap($conf['flashVersion'], $conf['flashVersion.']);
		if (!$flashVersion) {
			$flashVersion = '9';
		}

		$replaceElementIdString = uniqid('mmswf');
		$GLOBALS['TSFE']->register['MMSWFID'] = $replaceElementIdString;

		$alternativeContent = $this->cObj->stdWrap($conf['alternativeContent'], $conf['alternativeContent.']);

		$layout = $this->cObj->stdWrap($conf['layout'], $conf['layout.']);
		$layout = str_replace('###ID###', $replaceElementIdString, $layout);
		$layout = str_replace('###SWFOBJECT###', '<div id="' . $replaceElementIdString . '">' . $alternativeContent . '</div>', $layout);

		$width = $this->cObj->stdWrap($conf['width'], $conf['width.']);
		$height = $this->cObj->stdWrap($conf['height'], $conf['height.']);

		$width = $width ? $width : $conf[$conf['type'] . '.']['defaultWidth'];
		$height = $height ? $height : $conf[$conf['type'] . '.']['defaultHeight'];


		$embed = 'swfobject.embedSWF("' . $conf['player'] . '", "' . $replaceElementIdString . '", "' . $width . '", "' . $height . '",
		 		"' . $flashVersion . '", "' . $installUrl . '", ' . $conf['embedParams'] . ');';

		$content = $layout . '
			<script type="text/javascript">
				' . $flashvars . '
				' . $params . '
				' . $attributes . '
				' . $embed . '
			</script>';

		return $content;
	}

}


if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['tslib/content/class.tslib_content_shockwaveflashobject.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['tslib/content/class.tslib_content_shockwaveflashobject.php']);
}

?>