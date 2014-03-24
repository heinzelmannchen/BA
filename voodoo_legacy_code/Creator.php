<?php
require_once(realpath(dirname(__FILE__).'/../stdlib.php'));
class Creator
{
	private $entityName;
	private $config;
	private $outDir;
	private $force;
	private $tableFilter;
	private $schema;
	private $entity;
	private $classes;
	private $views;
	private $appPaths;
	private $noninteractive;
	private $skeletonDir;
	private $tplOptions = null;

	/*
	 * constructor
	 *
	 * @param {string} 			$backendDir (/path/to/backendDir/output/dir)		no default
	 *							Output directory of the php backendDir 
	 *							files
	 * @param {string} 			$frontendDir(/path/to/fronten/output/dir)			no default
	 *							Output directory of the frontendDir extjs
 	 *							files
	 * @param {string} 			$skeletonDir (/path/to/app/root)					default: null
	 *							Path to the templates(if null -> uses gaia 
	 *							templates)
	 * @param {<string>array} 	$tables array('table_a', 'table_b')					default: null
	 *							Array of tablenames. If null, all tables 
	 *							will be fetched.
	 * @param {boolean} 		$force (overwrites existing files)					default: false
	 *							Forces overwriting of existing files
	 *							(except Files.js if $noninteractive)
	 * @param {boolean} 		$views (includes tableviews)						default: false
	 *							Forces the class to search views and 
	 *							try to merge them with existing tables
	 * @param {<string>array} 	$classes array('extjs', 'php-entity')				default: ['all']
	 *							Only given class names will be 
 	 *							generated.
 	 *							all:				Backend- and Frontend-Classes
	 *							php:				Only backendDir Classes 
	 *												(Entity.php, 
	 *												EntityController.php)
	 *							extjs:				Frontend Classes (Views, 
	 *												Forms, Models..., EXCEPT Fields.js)
	 *							controller:			Form and List Controllers.
	 *							view:				Form and List Views.
	 *							php-entity:			Backend PHP Entity 
	 *												(Entitiy.php)
	 *							php-controller:		Backend PHP Entity Controller 
	 *												(EntityController.php)
	 *							form_controller:	Frontend Form Controllers.
	 *							list_controller:	Frontend List Controllers.
	 *							model:				Frontend Model.
	 *							proxy:				Frontend Proxy.
	 *							store:				Frontend Store.
	 *							fields:				Fiels.js.
	 * @param {boolean}			$noninteractive (class loves to chat)				default: false
	 *							If true: class will not do any output
	 *							(echo || print)
	 *
	 *
	 * @param {<something>}      tplOptions: An additional whatever that is available as
	 *                              $tplOptions within the template.
	 *
	 */
	public function __construct(
		$backendDir,
		$frontendDir,
		$skeletonDir = null, 
		$tables = null, 
		$force = null, 
		$views = null, 
		$classes = null,
		$noninteractive = null,
		$tplOptions = array())
	{
		$this->tplOptions = $tplOptions;
		$this->appPaths = array("gaiaDir" => realpath(dirname(__FILE__).'/../..'));
		$this->appPaths["backendDir"] = $backendDir;
		$this->appPaths["frontendDir"] = $frontendDir;

		foreach($this->appPaths as $tempPath)
		{
			if(!is_dir($tempPath))
			{
				@mkdir($tempPath, 0777, true);
			}
		}

		$this->skeletonDir = $skeletonDir;

		if($views != null)
		{
			$this->views = $views;
		}
		else
		{
			$this->views = false;
		}

		if($force != null)
		{
			$this->force = $force;
		}
		else
		{
			$this->force = false;
		}

		if($noninteractive != null)
		{
			$this->noninteractive = $noninteractive;
		}
		else
		{
			$this->noninteractive = false;
		}

		if ($classes != null)
		{
			$this->classes = $classes;
		}
		else
		{
			$this->classes = array('all');
		}

		if ($tables != null)
		{
			$this->tables = $tables;
		}

		date_default_timezone_set('Europe/Zurich');

		Config::set('LOGGING', array('type'=> 'none'));

		$this->config = Config::get('controller_creator',array());

		$dbconfig = Config::get('DB');

		if (!isset($dbconfig['main']['schema'])) 
		{
			die("ERROR: Please specify DB Scheme in config: Config var 'DB' must contain [conn][schema] value.\n");
		}

		$this->tableFilter = "";

		if (count($this->tables) > 0) 
		{
			$this->tableFilter = " AND table_name IN (";
			$tblstr = "";

			foreach($this->tables as $name) 
			{
				$tblstr .= DBH::conn()->quote($name) . ',';
			}

			$tblstr = trim($tblstr,',');
			$this->tableFilter .= "{$tblstr} ) ";
			$this->alert("Only processing the following tables given on command line:\n");
			$this->alert(join(', ',$tables)."\n\n\n");
		}

		$this->schema = $dbconfig['main']['schema'];

		$this->entity = array();
	}


	public function getEntities($schema = null)
	{
		if (!$schema) {
			$schema = $this->schema;
		}
		// fetch all tables
		$query = "
		SELECT * 
		FROM information_schema.tables 
		WHERE table_schema = '{$schema}' 
		AND table_type = 'BASE TABLE' {$this->tableFilter} ";

		if($this->views)
		{
			$query .= "OR table_schema = '{$schema}' AND table_type = 'VIEW'";
		}

		$query .= " ORDER BY table_name";

		$skip=false;
		$res = DBH::conn()->query($query)->fetchAll(PDO::FETCH_ASSOC);

		foreach($res as $line) 
		{		
			// depending on the DB system the col names can be lower or upper case of the information schema:
			if (isset($line['TABLE_NAME'])) 
			{
				$tableName = $line['TABLE_NAME'];
			} 
			else if (isset($line['table_name'])) 
			{
				$tableName = $line['table_name'];
			} 
			else 
			{
				throw new Exception("ERROR: Column 'table_name' in information_schema.tables not found!");
			}
			
			$entityName = Toolbox::make_a_camel($tableName);
			//$entityName = ucfirst(strtolower($tableName));
			//$entityName = preg_replace('/_([a-z])/e',"strtoupper('\\1')",$entityName);
			
			$query = "
			SELECT * 
			FROM information_schema.columns 
			WHERE table_schema = '{$schema}' 
			AND table_name = '{$tableName}'
			ORDER BY ordinal_position
			";
			$colres = DBH::conn()->query($query)->fetchAll(PDO::FETCH_ASSOC);
			
			$cols = array();

			foreach($colres as $col) 
			{
				// depending on the DB system the col names can be lower or upper case of the information schema:
				if (isset($col['COLUMN_NAME'])) 
				{
					$colName = $col['COLUMN_NAME'];
				} 
				else if (isset($col['column_name'])) 
				{
					$colName = $col['column_name'];
				} 
				else 
				{
					throw new Exception("ERROR: Column 'column_name' in information_schema.columns not found!");
				}
				
				if ($colName != 'id') 
				{
					if (isset($col['DATA_TYPE']))
					{
						$typeName = 'DATA_TYPE';
					}
					else if (isset($col['data_type']))
					{
						$typeName = 'data_type';
					}

					$cols[$colName] = $col[$typeName];
				}
			}



			$entity = new stdClass();

			$entity->name = $entityName;
			$entity->table = $tableName;
			$entity->view = null;
			$entity->properties = array();
			$entity->viewProperties = null;
			$entity->allProperties = array();
			$entity->type = $line['table_type'];

			if (isset($line['TABLE_TYPE']))
			{
				$entity->type = $line['TABLE_TYPE'];
			}
			else if (isset($line['table_type']))
			{
				$entity->type = $line['table_type'];
			}

			$cols[$colName] = $col[$typeName];

			$entityProperties = '';
			foreach ($cols as $col => $type) 
			{
				$properties = new stdClass();
				$properties->name = $col;

				switch (strtolower($type)) 
				{
					case "integer":
					case "int":
					case "bigint":
						$properties->type = "int";
						break;
					case "character varying":
					case "character":
					case "varchar":
						$properties->type = "string";
						break;
					case "timestamp with time zone":
					case "timestamp without time zone":
					case "timestamp":
						$properties->type = "timestamp";
						break;
					case "date":
						$properties->type = "date";
						break;
					case "boolean":
					case "tinyint":
						$properties->type = "boolean";
						break;
					case "double":
					case "double precision":
					case "float":
					case "real":
						$properties->type = "float";
						break;
					default:
						$properties->type = "string";
						break;
				}

				$entity->properties[] = $properties;
			}

			$this->entity[] = $entity;
		}

		if($this->views)
		{
			foreach($this->entity as $tempEntity1)
			{
				foreach($this->entity as $tempEntity2)
				{
					if('v_'.$tempEntity1->table == $tempEntity2->table)
					{
						$tempEntity1->view = $tempEntity2->table;
						$tempEntity1->viewProperties = array();

						$this->alert("View found for table \"$tempEntity1->table\". Try to merge.\n");

						foreach($tempEntity2->properties as $tempProp1)
						{
							$found = false;
							foreach($tempEntity1->properties as $tempProp2)
							{
								if($tempProp1->name == $tempProp2->name)
								{
									$found = true;
								}
							}

							if(!$found)
							{
								$tempEntity1->viewProperties[] = $tempProp1;
							}
							$tempEntity1->allProperties[] = $tempProp1;
						}
					}
				}
			}
		}

		return $this->entity;
	}

	public function go($smartyPath = null)
	{
		try
		{
			$fieldsObj = new stdClass();
			$fieldsObj->properteis = array();

			//Smarty Config
			if($smartyPath == null)
			{
				$smartyPath = Config::get('GAIA_SMARTY_PATH').'/Smarty.class.php';
			}
			require_once($smartyPath);

			$smarty = new Smarty();
			$smarty->setCompileDir(Config::get('GAIA_SMARTY_COMPILE_DIR','/tmp/'));
			$smarty->setCacheDir(Config::get('GAIA_SMARTY_CACHE_DIR','/tmp/'));
			$smarty->left_delimiter = "{{";
			$smarty->right_delimiter = "}}";

			$smarty->assign('tplOptions',$this->tplOptions);
			
			//PHP Entities
			$entityTpl = new stdClass();
			$entityTpl->validation = array('php-entity', 'php');
			$entityTpl->template = '/Entity.php.tpl';
			$entityTpl->output = $this->appPaths["backendDir"].'/entities/{REALNAME}.php';
			$entityTpl->fileName = null;
			
			//PHP Controllers
			$controllerTpl = new stdClass();
			$controllerTpl->validation = array('php-controller', 'php');
			$controllerTpl->template = '/Controller.php.tpl';
			$controllerTpl->output = $this->appPaths["backendDir"].'/controllers/{REALNAME}Controller.php';
			$controllerTpl->filename = null;
			
			//ExtJS Forms
			$formTpl = new stdClass();
			$formTpl->validation = array('form', 'extjs', 'view');
			$formTpl->template = '/extjs/Form.js.tpl';
			$formTpl->output = $this->appPaths["frontendDir"].'/view/{TABLENAME}';
			$formTpl->fileName = '/Form.js';
			
			//ExtJS Form Controllers
			$formControllerTpl = new stdClass();
			$formControllerTpl->validation = array('form_controller', 'extjs', 'controller');
			$formControllerTpl->template = '/extjs/FormController.js.tpl';
			$formControllerTpl->output = $this->appPaths["frontendDir"].'/controller/{TABLENAME}';
			$formControllerTpl->fileName = '/Form.js';
			
			//ExtJS Lists
			$listTpl = new stdClass();
			$listTpl->validation = array('list', 'extjs', 'view');
			$listTpl->template = '/extjs/List.js.tpl';
			$listTpl->output = $this->appPaths["frontendDir"].'/view/{TABLENAME}';
			$listTpl->fileName = '/List.js';
			
			//ExtJS List Controllers
			$listControllerTpl = new stdClass();
			$listControllerTpl->validation = array('list_controller', 'extjs', 'controller');
			$listControllerTpl->template = '/extjs/ListController.js.tpl';
			$listControllerTpl->output = $this->appPaths["frontendDir"].'/controller/{TABLENAME}';
			$listControllerTpl->fileName = '/List.js';
			
			//ExtJS Model
			$modelTpl = new stdClass();
			$modelTpl->validation = array('model', 'extjs');
			$modelTpl->template = '/extjs/Model.js.tpl';
			$modelTpl->output = $this->appPaths["frontendDir"].'/model/{REALNAME}.js';
			$modelTpl->fileName = null;
			
			//ExtJS Proxy
			$proxyTpl = new stdClass();
			$proxyTpl->validation = array('proxy', 'extjs');
			$proxyTpl->template = '/extjs/Proxy.js.tpl';
			$proxyTpl->output = $this->appPaths["frontendDir"].'/proxy/{REALNAME}.js';
			$proxyTpl->fileName = null;
			
			//ExtJS Store
			$storeTpl = new stdClass();
			$storeTpl->validation = array('store', 'extjs');
			$storeTpl->template = '/extjs/Store.js.tpl';
			$storeTpl->output = $this->appPaths["frontendDir"].'/store/{REALNAME}.js';
			$storeTpl->fileName = null;


			$paths = array();
			$paths[] = $entityTpl;
			$paths[] = $formTpl;
			$paths[] = $controllerTpl;
			$paths[] = $formControllerTpl;
			$paths[] = $listTpl;
			$paths[] = $listControllerTpl;
			$paths[] = $modelTpl;
			$paths[] = $proxyTpl;
			$paths[] = $storeTpl;


			foreach($this->entity as $tempEntity)
			{
				$smarty->assign('entity', $tempEntity);

				foreach($paths as $template)
				{
					if($this->contains($template->validation, $this->classes) && strtolower($tempEntity->type) != 'view')
					{
						$output = $template->output;
						$output = str_replace('{TABLENAME}', $tempEntity->table, $output);
						$output = str_replace('{REALNAME}', $tempEntity->name, $output);

						if($template->fileName != null)
						{

							if(!is_dir($output))
							{
								@mkdir($output, 0755, true);
							}

							$output = $output.$template->fileName;
						}
						else
						{
							$dir = dirname($output);
							@mkdir($dir, 0755, true);
						}
						
						if(file_exists($output) && !$this->force)
						{
							$this->alert("Not Forced: File $output exists -> skipping\n");
						}
						else
						{
							file_put_contents($output, $smarty->fetch('file:'.$this->getSkeleton($template->template)));
							
							if($this->contains($template->validation, array('extjs')))
							{
								$cmd = "env js-beautify -r -m -j $output";
								@exec($cmd, $lines, $ret);
							}
							
							$this->alert("Generated: $output.\n");						
						}
					}
				}

				foreach($tempEntity->properties as $tempProp)
				{
					$fieldsObj->properties[] = $tempProp->name;
				}
			}

			//Set _KP.FIELDS

			$forceFields = false;

			if(!$this->noninteractive && file_exists($this->appPaths["frontendDir"].'/tool/Fields.js') && $this->contains(array('fields'), $this->classes))
			{
				$this->alert("Fields.js exists. Overwrite it? [NO]:\n");
				$in = trim(fgets(STDIN));
				if (strlen($in)==0)
				{
					$in="NO";
				}

				if ($this->parseYesNoString($in))
				{
					$forceFields = true;
				}
			}
			else
			{
				$forceFields = true;
			}
			

			if($this->contains(array('fields'), $this->classes) && ($forceFields || $this->noninteractive))
			{
				if(!is_dir($this->appPaths["frontendDir"].'/tool'))
				{
					mkdir($this->appPaths["frontendDir"].'/tool');
				}

				$fieldsObj->properties = array_unique($fieldsObj->properties);
				sort($fieldsObj->properties);
				$smarty->assign('entity', $fieldsObj);
				file_put_contents($this->appPaths["frontendDir"].'/tool/Fields.js', $smarty->fetch('file:'.$this->appPaths["gaiaDir"].'/lib/skeletons/extjs/Fields.js.tpl'));
				$this->alert('Fields.js generated'."\n");
			}

			return true;
		}
		catch(Exception $e)
		{
			if(!$this->noninteractive)
			{
				$this->doOutput($e);
			}
			else
			{
				throw $e;
			}

			return false;
		}
	}

	private function contains($arr1, $arr2)
	{
		$found = false;

		foreach($arr1 as $a)
		{
			foreach($arr2 as $b)
			{
				if($a == $b || $b == 'all')
				{
					$found = true;
				}
			}
		}

		return $found;
	}

	private function parseYesNoString($in){
		$in = strtolower($in);
		$yesses = array("yes","y","ja","j","oui","yarrgh!","foshizzle");
		$noes = array("no","n","nein","non","njet","hellzno");
		if (in_array($in,$yesses))
			return true;
		if (in_array($in,$noes))
			return false;
		return null;
	}


	private function alert($message)
	{
		if(!$this->noninteractive)
		{
			echo $message;
		}
	}

	private function doOutput($message)
	{
		echo $message;
	}


	private function getSkeleton($path)
	{
		if($this->skeletonDir != null)
		{
			if(file_exists($this->skeletonDir.$path))
			{
				return $this->skeletonDir.$path;
			}
			else
			{
				$this->alert("Template ".$this->skeletonDir.$path." does not exist -> use gaia template\n");
				return $this->appPaths['gaiaDir'].'/lib/skeletons'.$path;
			}
		}
		else
		{
			return $this->appPaths['gaiaDir'].'/lib/skeletons'.$path;
		}
	}
}