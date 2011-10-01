<?php

if( !defined( 'MEDIAWIKI' ) ) {
	die("Not an entry point.\n");
}

$sksgIP = dirname(__FILE__) . '/';
$sksgScriptPath = $atwQgScriptPath . '/keywordsearch';
require_once( $sksgIP . 'SKS_Settings.php' ); // sets some defaults
require_once($IP.'/includes/specials/SpecialCategories.php');

// extension configuration options

/*$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Semantic Keyword Search',
	'author' => array('[http://mediawiki.org/wiki/User:Michael_A._White Michael White]', 'Daniel Herzig'), 
	'url' => '', 
	'descriptionmsg' => 'atwl_atwldescription'
);*/
$wgExtensionMessagesFiles['SemanticKeywordSearch'] = $sksgIP . 'SKS.i18n.php';
$wgExtensionFunctions[] = 'wfSKSSetup';

function wfSKSSetup() {
	global $wgAutoloadClasses, $wgSpecialPages, $wgAjaxExportList, $wgDebugLogGroups;
	global $smwgResultFormats, $sksgIP;	
	
	$wgAutoloadClasses['SKSQueryTree']     		= $sksgIP . 'includes/SKS_QueryTree.php';
	$wgAutoloadClasses['SKSQueryNode']     		= $sksgIP . 'includes/SKS_QueryTree.php';
	$wgAutoloadClasses['SKSKeywordData']   		= $sksgIP . 'includes/SKS_QueryTree.php';
	$wgAutoloadClasses['SKSKeyword']       		= $sksgIP . 'includes/SKS_QueryTree.php';
	$wgAutoloadClasses['SKSKeywordStore']  		= $sksgIP . 'includes/SKS_KeywordStore.php';
	$wgAutoloadClasses['SKSSpecialPage']   		= $sksgIP. 'specials/SKS_Special.php';
	$wgAutoloadClasses['SKSTableResultPrinter'] = $sksgIP. 'includes/SKS_QP_Table.php';
	
		
	
	$wgSpecialPages['KeywordSearch'] = 'SKSSpecialPage';
	
	$smwgResultFormats['skstable'] = 'SKSTableResultPrinter';
	
	$wgDebugLogGroups[] = 'logs/SemanticKeywordSearch.log';
	
	// todo: add AJAX logging function for clicking on result here
}

// this is apparently needed to load the $sksgExpectTypes global.  Otherwise things fail.
// we could switch it back to a static member variable but that would make less sense.
require_once($sksgIP . 'includes/SKS_QueryTree.php');

