<?php
// JUST A HACK FOR STOEFFEL FOR NOW, WILL BE MADE MUCH NICER IN THE FUTURE :-)
date_default_timezone_set('Europe/Zurich');
require_once(realpath(dirname(__FILE__).'/../stdlib.php'));
require_once(realpath(dirname(__FILE__).'/Creator.php'));
#@exec("say -v Trinoids 'Your application will be generated. There will be cake at the end!'");



//PARSE ARGS
if(php_sapi_name() != 'cli')
{
	die('Wrong SAPI!');
}

if($argv[1] == '?')
{
	usage_error();
}


$backendDir;
$jsDir;
$skeletonDir;
$tables;
$force;
$view;
$classes;
$noninteractive;
$athene = false;

if (Config::get('VOODOO_SKELETON_DIR',null) != null) {
	$skeletonDir = Config::get('VOODOO_SKELETON_DIR',null);
}

if (count($argv) < 2) 
{
	if(Config::get('VOODOO_BACKEND_DIR') != null && Config::get('VOODOO_FRONTEND_DIR') != null)
	{
		$backendDir = Config::get('VOODOO_BACKEND_DIR');
		$jsDir = Config::get('VOODOO_FRONTEND_DIR');
	}
	else
	{
		usage_error();
	}
}
else
{
	if(Config::get('VOODOO_BACKEND_DIR') != null && Config::get('VOODOO_FRONTEND_DIR') != null)
	{
		$backendDir = Config::get('VOODOO_BACKEND_DIR');
		$jsDir = Config::get('VOODOO_FRONTEND_DIR');
	}
	else
	{
		$backendDir = $argv[1].'/backend/app';
		$jsDir = $argv[1].'/app';
	}
}

$i = 0;

foreach($argv as $argument)
{
	if(substr($argument, 0, strlen('--tables=')) === '--tables=')
	{
		$tempTables = substr($argument, strlen('--tables='));
		if(strlen($tempTables) < 1)
		{
			usage_error();
		}
		else
		{
			$tables = explode(',', $tempTables);
		}
	}
	else if(substr($argument, 0, strlen('--classes=')) === '--classes=')
	{
		$tempClasses = substr($argument, strlen('--classes='));
		if(strlen($tempClasses) < 1)
		{
			usage_error();
		}
		else
		{
			$classes = explode(',', $tempClasses);
		}
	}
	else if($argument == '--force')
	{
		$force = true;
	}
	else if($argument == '--views')
	{
		$view = true;
	}
	else if($argument == '--noninteractive')
	{
		$noninteractive = true;
	}
	else if($argument == '--athene')
	{
		$athene = true;
	}
	else if(substr($argument, 0, strlen('--skeletondir=')) === '--skeletondir=')
	{
		$skeletonDir = substr($argument, strlen('--skeletondir='));
		if(strlen($skeletonDir) < 1)
		{
			usage_error();
		}
	}
	else if($i > 1)
	{
		print "\nerror near $argument\n";
		usage_error();
	}
	else
	{
		$i++;
	}
}
echo "\n\nSettings:\n";
echo "_______________________________________________________________\n";
printIfSet($backendDir, "Backend dir: 		");
printIfSet($jsDir, "Frontend dir: 		");
printIfSet($skeletonDir, "Skeleton dir: 		");
printIfSet($tables, "Tables: 		");
printIfSet($force, "Force: 			");
printIfSet($view, "Views: 			");
printIfSet($classes, "Classes: 		");
printIfSet($noninteractive, "Non interactive: 	");

echo "\n\nDo you want to continue[YES]?\n";

$response = strtolower(trim(fgets(STDIN)));

if ($response != 'yes' && $response != 'y' && strlen($response) != 0)
{
	die("Operation aborted...\n");
}


$creator = new Creator(
				$backendDir,
				$jsDir,
				$skeletonDir,
				$tables, 
				$force, 
				$view, 
				$classes,
				$noninteractive);

$res = $creator->getEntities();
//print_r($res);
$blub = $creator->go();
//print $blub;

if($athene == true) {
	include(realpath(dirname(__FILE__).'/athene/run_athene.php'));
}


function printIfSet($value, $prefix)
{
	if($value != null)
	{
		if(is_array($value))
		{
			echo $prefix;
			$output = "";
			foreach($value as $parts)
			{
				$output .= $parts.', ';
			}

			$output = substr($output, 0, -2);
			echo $output."\n";
		}
		else if(is_bool($value))
		{
			if($value)
			{
				echo $prefix."Yes\n";
			}
			else
			{
				echo $prefix."No\n";
			}
		}
		else
		{
			echo $prefix.$value."\n";
		}
	}
}

function usage_error()
{
	echo "\n\nUSAGE:\n";
	echo "	voodoo.php outputDir	[--tables=a,b,c] [--skeletondir=/path/to/dir] [--classes=extjs,php,...] 
					[--force] [--views] [--noninteractive]\n

___________________________________________________________________________________________________________________

outputDir 		Path to your output directory (/var/www/MyProj).
			Will generate \"/var/www/MyProj/app\" and \"/var/www/MyProj/backend/app\"
___________________________________________________________________________________________________________________

--tables= 		Names of the tables you want to run throug voodoo(comma seperated -> --tables=tbl_foo,tbl_bar).
			{default: whole shema}
___________________________________________________________________________________________________________________

--skeletondir= 		Path to the alternateive skeletons (/var/www/MyProj/backend/app/skeletons). 
			{default: gaiadir}		
___________________________________________________________________________________________________________________

--classes=		Classes you want to let voodoo generate(comma seperated -> --classes=php,model,proxy):
			{default: 'all'}	
				all:				Backend- and Frontend-Classes
				php:				Only backend Classes (Entity.php, EntityController.php)
				extjs:				Frontend Classes (Views, Forms, Fields, Models...)
				list:               Frontend List class
				form:               Frontend Form class
				controller:			Form and List Controllers.
				view:				Form and List Views.
				php-entity:			Backend PHP Entity (Entitiy.php)
				php-controller:			Backend PHP Entity Controller (EntityController.php)
				form_controller:		Frontend Form Controllers.
				list_controller:		Frontend List Controllers.
				model:				Frontend Model.
				proxy:				Frontend Proxy.
				store:				Frontend Store.
				fields:				Fiels.js.
___________________________________________________________________________________________________________________

--views 		Include views. (ENTITY: \"name\"	ENTITY-VIEW: \"v_name\")
			{default: false}
___________________________________________________________________________________________________________________

--force 		Overwrite existing files.
			{default: false}
___________________________________________________________________________________________________________________

--noninteractive 	Forces the app not to do any output on screen.
			{default: false}
";
	die(" ");
}

