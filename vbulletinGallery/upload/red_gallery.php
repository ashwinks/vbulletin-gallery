<?php

	// ####################### SET PHP ENVIRONMENT ###########################
	//ini_set('display_errors', 1);
	//error_reporting(E_ALL);

	// #################### DEFINE IMPORTANT CONSTANTS #######################
	define('THIS_SCRIPT', 'red_gallery');
	define('CSRF_PROTECTION', false);

	// ################### PRE-CACHE TEMPLATES AND DATA ######################
	// get special phrase groups
	$phrasegroups = array();

	// get special data templates from the datastore
	$specialtemplates = array();

	// pre-cache templates used by all actions
	$globaltemplates = array(
	    'redink_gallery_page',
		'redink_gallery_picture_bit'
	);

	// pre-cache templates used by specific actions
	$actiontemplates = array();

	// ######################### REQUIRE BACK-END ############################
	require_once('./global.php');
	require_once(DIR . '/includes/functions_file.php');
	require_once(DIR . '/includes/class_bootstrap_framework.php');
	require(DIR . '/redinkdesign/gallery/gallery.php');

	$vbulletin->input->clean_array_gpc('r', array(
		'page' => TYPE_UINT,
		'image' => TYPE_UINT,
		'dateline' => TYPE_UINT,
		'u' => TYPE_UINT
	));
	
	if (!$vbulletin->options['reg_gallery_enable']){
		print_no_permission();
	}

	if (!$userinfo = verify_id('user', $vbulletin->userinfo['userid'], 0, 1, FETCH_USERINFO_USERCSS)){
		$userinfo = array('userid'=>'0','usergroupid'=>'1');
	}
	
	cache_permissions($userinfo, false);
	
	if ($userinfo){
		if ($userinfo['usergroupid'] == 4 AND !($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])){
			print_no_permission();
		}
	}
	
	/**
		Handle Image Serving
	 */
	if ($vbulletin->GPC['image'] > 0){

		$rg = new RedGallery();
		$file_name = $vbulletin->GPC['image'] . '.jpg';

		$path = $rg->getCacheImagePath($vbulletin->GPC['u'], $vbulletin->GPC['image']);
		$file_contents = file_get_contents($path);
		
		header('Pragma:');
		header('Cache-control: max-age=31536000, private');
		header('Expires: ' . gmdate("D, d M Y H:i:s", time() + 31536000) . ' GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $vbulletin->GPC['dateline']) . ' GMT');
		header('ETag: "' . $vbulletin->GPC['image'] . '"');
		header('Accept-Ranges: bytes');
		header("Content-disposition: inline; $file_name");
		header('Content-transfer-encoding: binary');
		header("Content-type: image/jpeg");
		
		echo $file_contents;
		die();
	}
	
	$navbits = construct_navbits(array('' => 'Gallery'));
	$navbar = render_navbar_template($navbits);
	
	/**
	 * Start main isht
	 */
	$rg = new RedGallery();
		
	$perpage = $vbulletin->options['reg_gallery_images_per_page'];
	if (!empty($vbulletin->options['reg_gallery_use_cdn'])){
		$cdns = preg_split('/\n|\r/', $vbulletin->options['reg_gallery_use_cdn'] , -1, PREG_SPLIT_NO_EMPTY);
	}
	
	$total_images = $rg->getTotalImages();
	if ($vbulletin->GPC['page'] < 1) $vbulletin->GPC['page'] = 1;
	$total_pages = max(ceil($total_images / $perpage), 1);
	$page = ($vbulletin->GPC['page'] > $total_pages)? $total_pages : $vbulletin->GPC['page'];
	$start = ($page - 1) * $perpage;
	
	$pagenav = construct_page_nav($page, $perpage, $total_images, 'red_gallery.php?' . $vbulletin->session->vars['sessionurl_q']);

	$pictures = $rg->getImages($start, $perpage);
	$picturebits = '';
	foreach ($pictures as $picture){

		$templater = vB_Template::create('redink_gallery_picture_bit');

		if ($vbulletin->options['reg_gallery_enable_cache']){
		
			if (!$rg->cacheFileExists($picture['userid'], $picture['filedataid'])){
		 		$attachpath = fetch_attachment_path($picture['userid'], $picture['filedataid']);
				$new_picture = $rg->resizeImageMax($attachpath, $picture['extension'], 300, 400);
				$new_filename = $rg->saveToCache($picture['userid'], $new_picture, $picture['filedataid']);
				if (!$new_filename){
					$picture['image_url'] = "attachment.php?attachmentid={$picture['attachmentid']}";
					continue;
				}
			}
			
			if ($vbulletin->options['reg_gallery_image_serving'] == 'script'){
				$picture['image_url'] = "red_gallery.php?image={$picture['filedataid']}&dateline={$picture['dateline']}&u={$picture['userid']}";
			}elseif ($vbulletin->options['reg_gallery_image_serving'] == 'file_system'){
				$picture['image_url'] = $rg->getCacheImageUrlPath($picture['userid'], $picture['filedataid'], $vbulletion->options['bb_url']);
			}elseif ($vbulletin->options['reg_gallery_image_serving'] == 'cdn'){

				$picture['image_url'] = $rg->getCacheImageUrlPath($picture['userid'], $picture['filedataid'], $cdns[rand(0, count($cdns))]);
			}

		}else{
			
			$picture['image_url'] = "attachment.php?attachmentid={$picture['attachmentid']}";
			
		}
		
		$templater->register('picture', $picture);
		$picturebits .= $templater->render();

	}
	
	$templater = vB_Template::create('redink_gallery_page');
	$templater->register_page_templates();
	$templater->register('picturebits', $picturebits);
	$templater->register('perpage', $perpage);
	$templater->register('pagenav', $pagenav);
	$templater->register('navbar', $navbar);
	$templater->register('pagetitle', 'Gallery');
	
	print_output($templater->render());
