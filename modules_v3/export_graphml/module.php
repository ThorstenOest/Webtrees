<?php
/**
 * webtrees: online genealogy
 * Copyright (C) 2016 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleReportInterface;
use Fisharebest\Webtrees\Controller\PageController;
use Fisharebest\Webtrees\Database;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\GedcomTag;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Functions\Functions;
use Fisharebest\Webtrees\Functions\FunctionsPrint;
use Symfony\Component\Finder\Tests\FakeAdapter\NamedAdapter;
use Symfony\Component\Finder\Iterator\FilenameFilterIterator;
use phpDocumentor\Reflection\DocBlock\Tag;
use Zend\Filter\Boolean;


/**
 * Class to export a family tree in graphml format
 *
 * This class provides code to exporta family tree in graphml format.
 * The graphml format can be imported by yed to generate family tree charts.
 * yed supports all-in-one charts.
 */
class ExportGraphmlModule extends AbstractModule implements 
		ModuleReportInterface {
	/**
	 * Return a report menu item for the graphml export
	 *
	 * When selecting the item it calls the function modAction with parameter
	 * mod_action=set_parameter.
	 *
	 * @return Menu The report menu item for the graphml export.
	 */
	/**
	 * public function getReportMenu() {
	 * return new Menu ( $this->getTitle (),
	 * 'module.php?mod=' .
	 *
	 *
	 *
	 *
	 *
	 * $this->getName () .
	 * '&amp;mod_action=set_parameter',
	 * 'menu-report-' . $this->getName (),
	 * array ('rel' => 'nofollow'
	 * ) );
	 * }*
	 */
	public function getReportMenu() {
		return new Menu ( 'Export tree', '#', '', array (), 
				array (
						'rel' => new Menu ( "as graphml", 
								'module.php?mod=' . $this->getName () .
										 '&amp;mod_action=set_parameter_graphml', 
										'menu-report-' . $this->getName (), 
										array ('rel' => 'nofollow' 
										) ),
						'rel2' => new Menu ( "as latex", 
								'module.php?mod=' . $this->getName () .
								 '&amp;mod_action=set_parameter_latex', 
								'menu-report-' . $this->getName (), 
								array ('rel' => 'nofollow' 
								) ) 
				) );
	}
	
	/**
	 * Returns the title on tabs, menu
	 *
	 * @return string Title of the report
	 */
	public function getTitle() {
		return I18N::translate ( 'Export as graphml or latex' );
	}
	
	/**
	 * Returns the title in the report sub-menu
	 *
	 * @return string Title of the report
	 */
	public function getReportTitle() {
		return I18N::translate ( 'Export as graphml or latex' );
	}
	
	/**
	 * A sentence describing what this module does.
	 *
	 * This text appears on the
	 * admin pages where the module can be activeated.
	 *
	 * @return string
	 */
	public function getDescription() { // This text also appears in the .XML file - update both together
		return I18N::translate ( 
				'Export family tree in graphml format for yed or in latex format.' );
	}
	
	/**
	 * Default access level for this module
	 *
	 * @return int
	 */
	public function defaultAccessLevel() {
		return Auth::PRIV_PRIVATE;
	}
	
	/**
	 * This is the main entry function of this class.
	 *
	 * Depending on the parameter $mod_action export form is opened to define the
	 * export format or the export is started.
	 *
	 * @param string $mod_action
	 *        	= "set_parameter"
	 *        	opens a form to get parameter for the export,
	 *        	= "export" writes the graphml file
	 */
	public function modAction($mod_action) {
		global $WT_TREE;
		
		switch ($mod_action) {
			case 'set_parameter_graphml' :
				// open a form to define the export format
				$this->setParameterGraphml ();
				break;
			
			case 'set_parameter_latex' :
				// open a form to define the export format
				$this->setParameterLatex ();
				break;
			
			case 'export_latex' :
			case 'export_graphml' :
				// file name is set to the tree name
				$download_filename = $WT_TREE->getName ();
				$extension = ($mod_action == 'export_latex') ? ".tex" : ".graphml";
				if (strtolower ( substr ( $download_filename, - 8, 8 ) ) !=
						 $extension) {
					$download_filename .= $extension;
				}
				
				// Stream the file straight to the browser.
				header ( 'Content-Type: text/plain; charset=UTF-8' );
				header ( 
						'Content-Disposition: attachment; filename="' .
								 $download_filename . '"' );
				$stream = fopen ( 'php://output', 'w' );
				// Set Byte Order Mark
				fwrite ( $stream, pack ( "CCC", 0xef, 0xbb, 0xbf ) );
				if ($mod_action == "export_graphml") {
					$this->exportGraphml ( $WT_TREE, $stream );
				} else {
					$this->exportLatex ( $WT_TREE, $stream );
				}
				fclose ( $stream );
				
				// exit;
				break;
			
			case 'download_settings_graphml' :
			case 'download_settings_latex' :
				// download the formula data into a local file
				// file name is set to the tree name
				$download_filename = ($mod_action == 'download_settings_graphml') ? "export_graphml_settings.txt" : "export_latex_settings.txt";
				
				// Stream the file straight to the browser.
				header ( 'Content-Type: text/plain; charset=UTF-8' );
				header ( 
						'Content-Disposition: attachment; filename="' .
								 $download_filename . '"' );
				$stream = fopen ( 'php://output', 'w' );
				// Set Byte Order Mark
				fwrite ( $stream, pack ( "CCC", 0xef, 0xbb, 0xbf ) );
				
				// write parameter
				fwrite ( $stream, base64_encode ( serialize ( $_GET ) ) );
				
				fclose ( $stream );
				
				// exit;
				break;
			
			case 'upload_settings_graphml' :
			case 'upload_settings_latex' :
				// upload the formula data into a local file
				// get temporary file name of the uploaded file on the server
				$f = $_FILES ['uploadedfile'] ['tmp_name'];
				// now set the form fields
				$stream = fopen ( $f, 'r' );
				$d = fread ( $stream, filesize ( $f ) );
				$settings = unserialize ( base64_decode ( $d ) );
				
				// update web side with uploaded values
				if ($mod_action == "upload_settings_graphml") {
					$this->setParameterGraphml ( $settings );
				} else {
					$this->setParameterLatex ( $settings );
				}
				;
				
				// exit;
				break;
			
			default :
				http_response_code ( 404 );
		}
	}
	
	/**
	 * Get the help text
	 *
	 * This function returns a help text for individuals and families.
	 * Do not include here " or \" but only ' or \'
	 *
	 * @param string $s
	 * @return string
	 */
	private function getHelpText($s) {
		$help_array["individuals"] = array(
				array('GivenName',
						'',
						I18N::translate ( 'position list of given names' ) . ', \'.\' ' .
						I18N::translate ( 'for abbreviation'),
						'@GivenName&1,2,3.@'),
				array('SurName','', '-', '@SurName@'),
				array('NickName','', '-', '@NickName@'),
				array('BirthDate, DeathDate, FeFactDate', '',
						I18N::translate ( 'PHP date format specification' ),
						'@DeathDate&%j.%n.%Y@'),
				array('BirthPlace, DeathPlace, FeFactPlace', '',
						I18N::translate ('list of positions, exclusion followed after' ) . ' /',
						'@DeathPlace&2,3/USA@'),
				array('FactXXXX', '',
						I18N::translate ( 'position in the ordered fact list' ),
						'@FactOCCU&1,2,-1@'),
				array('Portrait', '',
						'fallback ' . I18N::translate ( 'or' ) .' silhouette',
						'@Portrait&fallback@'),
				array('Gedcom','', '-','@Gedcom@'),
				array('Remove', '',
						I18N::translate ('String to be removed' ),
						'@Remove&,@'),
				array('Replace', '',
						I18N::translate ('String to be replaced & replacement' ),
						'@Replace&:&\\@'),
				array('ForeachXXXX', 
						I18N::translate ('Foreach loop with XXXX=FAMS, Children' ),
						'-', '@ForeachFAMS@'),
				array('ForeachFactOuter', 
						I18N::translate ('Foreach loop over fact types given as format' ),
						I18N::translate ('Comma separated list of facts'), '@ForeachFactOuter&OCCU,EDUC@'),
				array('ForeachFactInner', 
						I18N::translate ('Foreach loop over facts within in ForeachFactOuter loop' ),
						'-', '@ForeachFactInner@'),
				array('FeFactType, FeAttributeType', 
						I18N::translate ('Returns the fact type within a ForeachFactOuter loop' ),
						'IfExists (nothing is returned if no facts exists)&prefix&postfix', 
						
				'@FeFactType&IfExists&\underline{&}:@'),
				array('FeFactValue', 
						I18N::translate ('Returns the fact value within a ForeachFactInner loop' ),
						'-', '@FeFactValue@'),
				array('ForeachMedia', 
						I18N::translate ('Foreach loop over media object' ),
						'1. Comma separated list of types 2. Comma separated list of formats', 
						'@ForeachMedia&photo&jpg,png@'),
				array('FeMediaFile', 
						I18N::translate ('Returns the media file name within a ForeachMedia loop' ),
						'NoExtension if extension should be removed', '@FeMediaFile&NoExtension@'),
				array('FeMediaCaption', 
						I18N::translate ('Returns the media title within a ForeachMedia loop' ),
						'-', '@FeMediaCaption@'),
				array('ForeachReference', 
						I18N::translate ('Foreach loop over references' ),
						'-', 
						'@ForeachReferences@'),
				array('FeReferenceName', 
						I18N::translate ('Returns the reference id within a ForeachReference loop' ),
						'-', '@FeReferenceName@'),
				array('Counter', 
						I18N::translate ('Counter in foreach loop' ),
						'-', '@Counter@')
		);
		$help_array["families"] = array(
				array('MarriageDate',
						'',
						I18N::translate ( 'PHP date format specification' ),
						'@MarriageDate&%j.%n.%Y@'),
				array('MarriagePlace',
						'',
						I18N::translate ('list of positions, exclusion followed after' ) . ' /',
						'@MarriagePlace&2,3/USA@'),
				array('Marriage',
						'',
						'-',
						'@Marriage@'),
				array('FactXXXX',
						'',
						I18N::translate ( 'position in the ordered fact list' ),
						'@FactOCCU&1,2,-1@'),
				array('Gedcom',
						'',
						'-',
						'@Gedcom@'),
				array('Remove',
						'',
						I18N::translate ('String to be removed' ),
						'@Remove&,@')
		);
		$help_array["latex"] = $help_array["individuals"];
		array_push($help_array["latex"],
				array('FatherGivenName, MotherGivenName, SpouseGivenName',
						'',
						I18N::translate ( 'position list of given names' ) . ', \'.\' ' .
						I18N::translate ( 'for abbreviation' ),
						'@GivenName&1,2,3.@'),
				array('FatherSurName, MotherSurName, SpouseSurName',
						'',
						'-',
						'@SurName@'),
				array('Id, FatherId, MotherId, SpouseId',
						'',
						'no, gen_no or xref',
						'@Id&gen_no@'),
				array('',
						'',
						I18N::translate ('' ),
						'@@')
				);
				
		$help_text = 
			'<table class=\'facts_table width50\'>' .
			'<td class=\'optionbox\' colspan=\'5\'>' . I18N::translate ( 
			'List of allowed keywords to be used in the templates.' ) . ' ' .
			I18N::translate ( 
				'The first four characters within a template define the brackets used to group tag areas and the identifier for a tag and the format part, e.g. {}@&.' ) . 
				'<br><br><table border=\'1\'>' .
			'<tr><th>' . I18N::translate ( 'Tag' ) . '</th><th>' .
			I18N::translate ( 'Description' ) . '</th><th>' .
			I18N::translate ( 'Format' ) . '</th><th>' .
			I18N::translate ( 'Example given identifier @&' ) . '</th></tr>' ;
		
			foreach ($help_array[$s] as $this_help) {
			$help_text .= '<tr><td>' . $this_help[0] .
						'</td><td>' . $this_help[1] .
						'</td><td>' . $this_help[2] . 
						'</td><td>' . $this_help[3] . '</td></tr>';
		}
		$help_text .= '</table>';
			
		return $help_text;
	}
	
	/**
	 * Generate a form to define the graphml format
	 *
	 * This function generates a form to define the export parameter
	 * and to trigger the export by submit.
	 *
	 * @param array $settings
	 *        	The setting in the form
	 */
	private function setParameterGraphml($settings = NULL) {
		global $controller;
		
		// generate a standard page
		$controller = new PageController ();
		$controller->setPageTitle ( $this->getTitle () )->pageHeader ();
		
		$directory = WT_MODULES_DIR . $this->getName ();
		
		// fillread settings if not passed
		if (is_null ( $settings )) {
			$filename = $directory . "/export_graphml_settings.txt";
			if (file_exists ( $filename )) {
				$myfile = fopen ( $filename, "r" );
				$settings = fread ( $myfile, filesize ( $filename ) );
				$settings = unserialize ( base64_decode ( $settings ) );
			} else {
				$settings = array ();
			}
		}
		;
				
		// header line of the form
		echo '<div id="reportengine-page">
		<form name="setupreport" method="get" action="module.php">
		<input type="hidden" name="mod" value=', $this->getName (), '>';
		
		
		echo '<table class="facts_table width50">
		<tr><td class="topbottombar" colspan="7">', I18N::translate ( 
				'Export tree in graphml format' ), '</td></tr>';
				
		/*
		 * Individual/family node text and description
		 *
		 * Reads the template from file and opens a textarea with the template
		 * used for the node text and node description  
		 *
		 */
		
		foreach ( array ("individuals","families" 
		) as $s1 ) {
			$help_text = addslashes($this->getHelpText($s1));
			echo '<tr><td class="descriptionbox width30 wrap" colspan="5">', I18N::translate ( 
					'Template for ' . $s1 );
			echo '<span class="icon-help" onclick="javascript:open(\'\', \'Help window\', \'height=600,width=800,resizable=yes\').document.write(\'<html>' . $help_text . '</html>\')"></span>';				
			echo '</td></tr>';
			
			foreach ( array ("label","description" 
			) as $s2 ) {
				echo '<tr><td class="descriptionbox width30 wrap">', I18N::translate ( 
						"Node " . $s2 ), '</td>';
				
				$name = $s1 . "_" . $s2 . "_template";
				$s = array_key_exists ( $name, $settings ) ? $settings [$name] : "";
				$nrow = substr_count ( $s, "\n" ) + 1;
				
				echo '<td class="optionbox" colspan="4">' . '<textarea rows="' .
						 $nrow . '" cols="100" name="' . $name . '">';
				echo $s;
				echo '</textarea></td></tr>';
			}
		}
		
		
		/*
		 * Box style header
		 *
		 * This is the header line for the block which defines the box styles.
		 * Different box styles can be defines for
		 * individuals (male, femal, unknown sex) and families.
		 */
		echo '<tr><td class="descriptionbox width30 wrap" rowspan="7">', I18N::translate ( 
				'Box style' ), '</td>';
		echo '<td class="descriptionbox width30 wrap"  colspan="1">', I18N::translate ( 
				'Male' ), '</td>';
		echo '<td class="descriptionbox width30 wrap"  colspan="1">', I18N::translate ( 
				'Female' ), '</td>';
		echo '<td class="descriptionbox width30 wrap"  colspan="1">', I18N::translate ( 
				'Unknown sex' ), '</td>';
		echo '<td class="descriptionbox width30 wrap"  colspan="1">', I18N::translate ( 
				'Family' ), '</td></tr>';
		
		/*
		 * Box style - box type
		 *
		 * Here the types of the boxes are defined.
		 */
		
		$choicelist = array ("BevelNode2","Rectangle","RoundRect","BevelNode",
				"BevelNodeWithShadow","BevelNode3","ShinyPlateNode",
				"ShinyPlateNodeWithShadow","ShinyPlateNode2","ShinyPlateNode3" 
		);
		echo '<tr>';
		foreach ( array ("male","female","unknown" 
		) as $s ) {
			$name = "node_style_" . $s;
			// $selected = $settings[$name];
			$selected = array_key_exists ( $name, $settings ) ? $settings [$name] : "BevelNode";
			
			echo '<td class="optionbox"  colspan="1">' . '<select name="' . $name .
					 '">';
			foreach ( $choicelist as $o ) {
				echo '<option value="' . $o . '"';
				if ($selected == $o)
					echo 'selected';
				echo '>' . $o . '</option>';
			}
			;
			/**
			 * '<option value="BevelNode2" selected>BevelNode2</option>' .
			 *
			 *
			 *
			 *
			 *
			 *
			 * '<option value="Rectangle">Rectangle</option>' .
			 * '<option value="RoundRect">RoundRect</option>' .
			 * '<option value="BevelNode">BevelNode</option>' .
			 * '<option value="BevelNodeWithShadow">BevelNodeWithShadow</option>' .
			 * '<option value="BevelNode3">BevelNode3</option>' .
			 * '<option value="ShinyPlateNode">ShinyPlateNode</option>' .
			 * '<option value="ShinyPlateNodeWithShadow">ShinyPlateNodeWithShadow</option>' .
			 * '<option value="ShinyPlateNode2">ShinyPlateNode2</option>' .
			 * '<option value="ShinyPlateNode3">ShinyPlateNode3</option>' .
			 * '</select></td>';
			 */
			echo '</select></td>';
		}
		
		$name = "node_style_family";
		// $selected = $settings[$name];
		$selected = array_key_exists ( $name, $settings ) ? $settings [$name] : "diamond";
		
		$choicelist = array ("rectangle","roundrectangle","ellipse",
				"parallelogram","hexagon","triangle","rectangle3d","octagon3d",
				"diamond","trapezoid","trapezoid2" 
		);
		
		echo '<td class="optionbox"  colspan="1">' .
				 '<select name="node_style_family">';
		foreach ( $choicelist as $o ) {
			echo '<option value="' . $o . '"';
			if ($selected == $o)
				echo 'selected';
			echo '>' . $o . '</option>';
		}
		;
		/**
		 * '<option value="rectangle">Rectangle</option>' .
		 *
		 *
		 *
		 *
		 *
		 *
		 * '<option value="roundrectangle">Round Rectangle</option>' .
		 * '<option value="ellipse">Ellipse</option>' .
		 * '<option value="parallelogram">Parallelogram</option>' .
		 * '<option value="hexagon">Hexagon</option>' .
		 * '<option value="triangle">Triangle</option>' .
		 * '<option value="rectangle3d">Rectangle 3D</option>' .
		 * '<option value="octagon3d">Octagon</option>' .
		 * '<option value="diamond" selected>Diamond</option>' .
		 * '<option value="trapezoid">Trapezoid</option>' .
		 * '<option value="trapezoid2">Trapezoid2</option>' .
		 */
		echo '</select></td></tr>';
		
		/*
		 * Box style - Fill color
		 *
		 * Here the fill colors of the boxes are defined.
		 */
		/**
		 * echo '<tr><td class="optionbox" colspan="1">' .
		 *
		 *
		 *
		 *
		 *
		 * I18N::translate ('Fill color') .
		 * '<input type="color" value="#ccccff" name="color_male"></td>';
		 * echo '<td class="optionbox" colspan="1">' . I18N::translate ('Fill color') .
		 * '<input type="color" value="#ffcccc" name="color_female"></td>';
		 * echo '<td class="optionbox" colspan="1">' . I18N::translate ('Fill color') .
		 * '<input type="color" value="#ffffff" name="color_unknown"></td>';
		 * echo '<td class="optionbox" colspan="1">' . I18N::translate ('Fill color') .
		 * '<input type="color" value="#ffffff" name="color_family"></td></tr>';
		 */
		echo '<tr><td class="optionbox" colspan="1">' .
				 I18N::translate ( 'Fill color' ) . '<input type="color" value="' .
				 (array_key_exists ( "color_male", $settings ) ? $settings ["color_male"] : "#ccccff") .
				 '" name="color_male"></td>';
		echo '<td class="optionbox" colspan="1">' .
				 I18N::translate ( 'Fill color' ) . '<input type="color" value="' .
				 (array_key_exists ( "color_female", $settings ) ? $settings ["color_female"] : "#ffcccc") .
				 '" name="color_female"></td>';
		echo '<td class="optionbox" colspan="1">' .
				 I18N::translate ( 'Fill color' ) . '<input type="color" value="' .
				 (array_key_exists ( "color_unknown", $settings ) ? $settings ["color_unknown"] : "#ffffff") .
				 '" name="color_unknown"></td>';
		echo '<td class="optionbox" colspan="1">' .
				 I18N::translate ( 'Fill color' ) . '<input type="color" value="' .
				 (array_key_exists ( "color_family", $settings ) ? $settings ["color_family"] : "#ffffff") .
				 '" name="color_family"></td></tr>';
		
		/*
		 * Box style - Border color
		 *
		 * Here the border colors of the boxes are defined.
		 */
		echo '<tr>';
		foreach ( array ("male","female","unknown" 
		) as $s ) {
			$name = "border_" . $s;
			echo '<td class="optionbox" colspan="1">' .
					 I18N::translate ( 'Border color' ) .
					 '<input type="color" value="' .
					 (array_key_exists ( $name, $settings ) ? $settings [$name] : "#ffffff") .
					 '" name="' . $name . '"></td>';
		}
		foreach ( array ("family" 
		) as $s ) {
			$name = "border_" . $s;
			echo '<td class="optionbox" colspan="1">' .
					 I18N::translate ( 'Border color' ) .
					 '<input type="color" value="' .
					 (array_key_exists ( $name, $settings ) ? $settings [$name] : "#ffffff") .
					 '" name="' . $name . '"></td>';
		}
		echo '</tr>';
		
		/*
		 * Box style - Box width
		 *
		 * Here the widths of the boxes are defined.
		 */
		echo '<tr>';
		foreach ( array ("male","female","unknown" 
		) as $s ) {
			$name = "box_width_" . $s;
			echo '<td class="optionbox" colspan="1">' .
					 I18N::translate ( 'Box width' ) .
					 '<input type="number" value="' .
					 (array_key_exists ( $name, $settings ) ? $settings [$name] : "120") .
					 '" name="' . $name . '"></td>';
		}
		$name = "box_width_family";
		echo '<td class="optionbox" colspan="1">' . I18N::translate ( 'Symbol' ) .
				 " " . I18N::translate ( 'width' ) . "/" .
				 I18N::translate ( 'height' ) . '<input type="number" value="' .
				 (array_key_exists ( $name, $settings ) ? $settings [$name] : "120") .
				 '" name="' . $name . '"></td></tr>';
		
		/*
		 * Box style - Border line width
		 *
		 * Here the widths of the border lines are defined.
		 */
		echo '<tr>';
		foreach ( array ("male","female","unknown","family" 
		) as $s ) {
			$name = "border_width_" . $s;
			echo '<td class="optionbox" colspan="1">' .
					 I18N::translate ( 'Border width' ) .
					 '<input type="number" value="' .
					 (array_key_exists ( $name, $settings ) ? $settings [$name] : "1") .
					 '" step="0.1" name="' . $name . '"></td>';
		}
		echo '</tr>';
		
		/*
		 * Box style - Font size
		 *
		 * Here the font sizes of the text are defined.
		 */
		echo '<tr>';
		foreach ( array ("male","female","unknown","family" 
		) as $s ) {
			$name = "font_size_" . $s;
			echo '<td class="optionbox" colspan="1">' .
					 I18N::translate ( 'Font size' ) .
					 '<input type="number" value="' .
					 (array_key_exists ( $name, $settings ) ? $settings [$name] : "10") .
					 '" step="1" name="' . $name . '"></td>';
		}
		echo '</tr>';
		
		/*
		 * Box style - default silhouettes
		 *
		 * Here the default silhouettes are defined.
		 */
		echo '<tr><td class="descriptionbox width30 wrap" rowspan="1">', I18N::translate ( 
				'Default portrait' ), '</td>';
		foreach ( array ("male","female","unknown" 
		) as $s ) {
			$name = "default_portrait_" . $s;
			echo '<td class="optionbox" colspan="1">
					<input type="text" size="30" value="' .
					 (array_key_exists ( $name, $settings ) ? $settings [$name] : "") .
					 '" name="' . $name . '"></td>';
		}
		
		echo '<td class="optionbox" colspan="1"/></tr>';
		
		/*
		 * Font type
		 *
		 * Here the font type is defined.
		 */
		$name = "font";
		$selected = (array_key_exists ( $name, $settings ) ? $settings [$name] : "");
		
		$choicelist = array ("Times New Roman","Dialog","Franklin Gothic Book",
				"Bookman Old Style","Lucida Handwriting" 
		);
		
		echo '<tr><td class="descriptionbox width30 wrap">', I18N::translate ( 
				'Font' ), '</td><td class="optionbox" colspan="4">' .
				 '<select name="font">';
		foreach ( $choicelist as $o ) {
			echo '<option value="' . $o . '"';
			if ($selected == $o)
				echo 'selected';
			echo '>' . $o . '</option>';
		}
		;
		/**
		 * '<option value="Times New Roman" selected>Times New Roman</option>' .
		 *
		 *
		 *
		 *
		 *
		 *
		 * '<option value="Dialog">Dialog</option>' .
		 * '<option value="Franklin Gothic Book">Franklin Gothic Book</option>' .
		 * '<option value="Bookman Old Style">Bookman Old Style</option>' .
		 * '<option value="Lucida Handwriting">Lucida Handwriting</option>' .
		 */
		echo '</select></td></tr>';
		
		/*
		 * Edge line width
		 *
		 * Here the width of edge lines is defined.
		 */
		echo '<tr><td class="descriptionbox width30 wrap">', I18N::translate ( 
				'Line width of edge' ), '</td><td class="optionbox" colspan="4">
			<input type="number" value="' .
				 (array_key_exists ( "edge_line_width", $settings ) ? $settings ["edge_line_width"] : "1") .
				 '" step="0.1" name="edge_line_width" min="1" max="7"></td></tr>';
		
		// Submit button<
		echo '<tr><td class="topbottombar" colspan="6">', '<button name="mod_action" value="export_graphml">', I18N::translate ( 
				'Export Family Tree' ), '</button>', '</td></tr>';
		// echo '</form>';
		
		// download settings
		// echo '<form name="download" method="get" action="module.php">
		// <input type="hidden" name="mod" value=', $this->getName (), '>';
		// <input type="hidden" name="mod_action" value="export">
		echo '<tr><td class="topbottombar" colspan="6">', '<button name="mod_action" value="download_settings_graphml">', I18N::translate ( 
				'Download Settings' ), '</button></td></tr>';
		echo '</table></form>';
		// echo '</table></form>';
		
		// echo '</div>';
		
		// upload settings
		// echo '<div id="reportengine-page">';
		echo '<table class="facts_table width50">';
		echo '<tr><td class="descriptionbox width30 wrap" colspan="5">', I18N::translate ( 
				'Upload/Download Settings' ), '</td></tr>';
		// header line of the form
		echo '<form name="upload" enctype="multipart/form-data" method="POST" 
				action="module.php?mod=' . $this->getName () .
				 '&mod_action=upload_settings_graphml">';
		// <input type="hidden" name="mod" value=', $this->getName (), '>';
		
		echo '<tr><td class="descriptionbox width30 wrap">', I18N::translate ( 
				'Read' ), '</td><td class="optionbox" colspan="4">', '<input name="uploadedfile" type="file"/>', '<button type="submit" value="upload_settings">', I18N::translate ( 
				'Upload Settings' ), '</button></td></tr>';
		echo '</table></form>';
		
		/**
		 * // download settings
		 * echo '<form name="download" method="get" action="module.php">
		 * <input type="hidden" name="mod" value=', $this->getName (), '>';
		 * // <input type="hidden" name="mod_action" value="export">
		 * echo '<tr><td class="descriptionbox width30 wrap">', I18N::translate (
		 * 'Write' ),
		 * '<td class="optionbox" colspan="4">',
		 * '<button name="mod_action" value="download_settings">', I18N::translate (
		 * 'Download Settings' ), '</button></td></tr>';
		 * echo '</table></form>';
		 */
		echo '</div>';
	}
	
	/**
	 * Generate a form to define the latexformat
	 *
	 * This function generates a form to define the export parameter
	 * and to trigger the export by submit.
	 *
	 * @param array $settings
	 *        	The setting in the form
	 */
	private function setParameterLatex($settings = NULL) {
		global $controller;
		
		// generate a standard page
		$controller = new PageController ();
		$controller->setPageTitle ( $this->getTitle () )->pageHeader ();
		
		$directory = WT_MODULES_DIR . $this->getName ();

		$help_text = addslashes($this->getHelpText("latex"));
		
		// fillread settings if not passed
		if (is_null ( $settings )) {
			$filename = $directory . "/export_latex_settings.txt";
			if (file_exists ( $filename )) {
				$myfile = fopen ( $filename, "r" );
				$settings = fread ( $myfile, filesize ( $filename ) );
				$settings = unserialize ( base64_decode ( $settings ) );
			} else {
				$settings = array ();
			}
		}
		;
		
		// header line of the form
		echo '<div id="reportengine-page">
		<form name="setupreport" method="get" action="module.php">
		<input type="hidden" name="mod" value=', $this->getName (), '>';
		// <input type="hidden" name="mod_action" value="export">
		
		echo '<table class="facts_table width50">
		<tr><td class="topbottombar" colspan="7">', I18N::translate ( 
				'Export tree in latex format' ), '</td></tr>';
		
		echo '<tr><td class="descriptionbox width30 wrap">', I18N::translate ( 
				"Reference Individual" ), '</td>';
		
		echo '<td class="optionbox" colspan="4">';


		echo '<label for="pid">' . I18N::translate('Enter an individual ID') . '</label>';
		echo '<input class="pedigree_form" data-autocomplete-type="IFSRO" type="text" name="refid" id="pid" size="5" value="">';
		echo ' ' . FunctionsPrint::printFindIndividualLink('pid');
		echo '</td>';
		
		/*
		 * Preamble
		 */
		echo '<tr><td class="descriptionbox width30 wrap" colspan="5">', I18N::translate ( 
				'Preamble' ), '</td></tr>';
		
		echo '<tr><td class="descriptionbox width30 wrap">', I18N::translate ( 
				"Preamble" ), '</td>';
		
		$name = "preamble";
		$s = (array_key_exists ( $name, $settings ) ? $settings [$name] : "");		
		$nrow = min(10,substr_count ( $s, "\n" ) + 1);
		
		echo '<td class="optionbox" colspan="4">' . '<textarea rows="' . $nrow .
				 '" cols="100" name="' . $name . '">';
		echo $s;
		echo '</textarea></td></tr>';
		
		/*
		 * document title
		 */
		echo '<tr><td class="descriptionbox width30 wrap" colspan="5">', I18N::translate ( 
				'Document Title' ), '</td></tr>';
		
		echo '<tr><td class="descriptionbox width30 wrap">', I18N::translate ( 
				"Title" ), '</td>';
		
		$name = "title";
		$s = (array_key_exists ( $name, $settings ) ? $settings [$name] : "");
		$nrow = min(10,substr_count ( $s, "\n" ) + 1);
		
		echo '<td class="optionbox" colspan="4">' . '<textarea rows="' . $nrow .
				 '" cols="100" name="' . $name . '">';
		echo $s;
		echo '</textarea></td></tr>';
		
		/*
		 * Individual text
		 *
		 */
		echo '<tr><td class="descriptionbox width30 wrap" colspan="5">', I18N::translate ( 
				'Template for individuals');

		echo '<span class="icon-help" onclick="javascript:open(\'\', \'Help window\', \'height=600,width=800,resizable=yes\').document.write(\'<html>' . $help_text . '</html>\')"></span>';
		
		echo  '</td></tr><tr><td class="descriptionbox width30 wrap">', I18N::translate ( 
				"Node label"), '</td>';
		
		$name = "individuals_label_template";
		$s = (array_key_exists ( $name, $settings ) ? $settings [$name] : "");
		
		$nrow = substr_count ( $s, "\n" ) + 1;
		
		echo '<td class="optionbox" colspan="4">' . '<textarea rows="' .
				 $nrow . '" cols="100" name="' . $name . '">';
		echo $s;
		echo '</textarea></td></tr>';

		/*
		 * Epilog
		 */
		echo '<tr><td class="descriptionbox width30 wrap" colspan="5">', I18N::translate (
				'Epilog' ), '</td></tr>';
		
		echo '<tr><td class="descriptionbox width30 wrap">', I18N::translate (
				"Epilog" ), '</td>';
		
		$name = "epilog";
		$s = (array_key_exists ( $name, $settings ) ? $settings [$name] : "");
		
		$nrow = min(10,substr_count ( $s, "\n" ) + 1);
		
		echo '<td class="optionbox" colspan="4">' . '<textarea rows="' . $nrow .
		'" cols="100" name="' . $name . '">';
		echo $s;
		echo '</textarea></td></tr>';
		
		/*
		 * Replacement for fact abbreviations
		 */
		echo '<tr><td class="descriptionbox width30 wrap" colspan="5">', I18N::translate (
				'Symbols to be used for facts' ), '</td></tr>';
		
		echo '<tr><td class="descriptionbox width30 wrap">', I18N::translate (
				"Symbols" ), '</td>';
		
		$name = "symbols";
		$s = (array_key_exists ( $name, $settings ) ? $settings [$name] : "");
		
		$nrow = min(10,substr_count ( $s, "\n" ) + 1);
		
		echo '<td class="optionbox" colspan="4">' . '<textarea rows="' . $nrow .
		'" cols="100" name="' . $name . '">';
		echo $s;
		echo '</textarea></td></tr>';
		
		/*
		 * default format for dates and places
		 *
		 */
		echo '<tr><td class="descriptionbox width30 wrap" colspan="5">', I18N::translate (
				'Default formats' ), '</td></tr>';
		
		foreach ( array ("date","place" 
		) as $s ) {
			echo '<tr><td class="descriptionbox width30 wrap" rowspan="1">', I18N::translate (
				'Default ' . $s . ' format' ), '</td>';

			$name = 'default_' . $s . '_format';
			echo '<td class="optionbox" colspan="1">
				<input type="text" size="30" value="' .
				(array_key_exists ( $name, $settings ) ? $settings [$name] : "") .
			 	'" name="' . $name . '"></td></tr>';
		}

		/*
		 * hierarchy for family tree and generation
		 *
		 */
		echo '<tr><td class="descriptionbox width30 wrap" colspan="5">', I18N::translate (
				'Document hierarchy' ), '</td></tr>';
		
		foreach ( array ("tree","generation"
		) as $s ) {
			echo '<tr><td class="descriptionbox width30 wrap" rowspan="1">', I18N::translate (
					'Hierarchy level for ' . $s), '</td>';
		
			$name = 'hierarchy_' . $s;
			echo '<td class="optionbox" colspan="1">
				<input type="text" size="30" value="' .
						(array_key_exists ( $name, $settings ) ? $settings [$name] : "") .
						'" name="' . $name . '"></td></tr>';
		}
		
		
		//echo '<td class="optionbox" colspan="1"/></tr>';
		
		
		
		// Submit button<
		echo '<tr><td class="topbottombar" colspan="6">', '<button name="mod_action" value="export_latex">', I18N::translate ( 
				'Export Family Tree' ), '</button>', '</td></tr>';
		
		// download settings
		// echo '<form name="download" method="get" action="module.php">
		// <input type="hidden" name="mod" value=', $this->getName (), '>';
		// <input type="hidden" name="mod_action" value="export">
		echo '<tr><td class="topbottombar" colspan="6">', '<button name="mod_action" value="download_settings_latex">', I18N::translate ( 
				'Download Settings' ), '</button></td></tr>';
		echo '</table></form>';
		// echo '</table></form>';
		
		// echo '</div>';
		
		// upload settings
		// echo '<div id="reportengine-page">';
		echo '<table class="facts_table width50">';
		echo '<tr><td class="descriptionbox width30 wrap" colspan="5">', I18N::translate ( 
				'Upload/Download Settings' ), '</td></tr>';
		// header line of the form
		echo '<form name="upload" enctype="multipart/form-data" method="POST" 
				action="module.php?mod=' . $this->getName () .
				 '&mod_action=upload_settings_latex">';
		// <input type="hidden" name="mod" value=', $this->getName (), '>';
		
		echo '<tr><td class="descriptionbox width30 wrap">', I18N::translate ( 
				'Read' ), '</td><td class="optionbox" colspan="4">', '<input name="uploadedfile" type="file"/>', '<button type="submit" value="upload_settings">', I18N::translate ( 
				'Upload Settings' ), '</button></td></tr>';
		echo '</table></form>';
		
		echo '</div>';
	}
	/**
	 * Get the given name in a predefined format
	 *
	 * This module returns the given name in a format defined by $format.
	 * Suppose the name is "Paul Micheal Patrick" then the full name is returned
	 * if no format is defined.
	 * If $format="1" then "Paul" is returned.
	 * If $format="1,3" then "Paul Patrick" is returned.
	 * If $format="1,." then "Paul M. P." is returned.
	 * If $format="." then "P. M. P." is returned.
	 * If $format="1,2." then "Paul M." is returned.
	 *
	 * @param Individual $record
	 *        	record for an idividual
	 * @param string $format
	 *        	The format of the given name. It is a comma separated
	 *        	list of numbers where each number stands for one of the given names. If a dot "."
	 *        	is given then all following given names are abbreviated.
	 * @return string The given name
	 */
	public static function getGivenName($record, $format = "") {
		// first get the given name

		$tmp = $record->getAllNames ();
		$givn = $tmp [$record->getPrimaryName ()] ['givn'];
		
		// if $format is given then apply the format
		if ($givn and $format) {
			$exp_givn = explode ( ' ', $givn );
			$count_givn = count ( $exp_givn );
			
			$exp_format = explode ( ",", $format );
			$givn = "";
			
			// loop over all parts of the given name and check if it is
			// specified in the format
			for($i = 0; $i < $count_givn; $i ++) {
				$s = ( string ) $i + 1;
				if (in_array ( $s, $exp_format )) {
					// given name to be included
					$givn .= " " . $exp_givn [$i];
				} elseif (in_array ( ".", $exp_format, true ) or
						 in_array ( ($i . '.'), $exp_format, true )) {
					// - if "." is included in the format list then all parts of the name
					// are included abbreviated
					// - a given name is also included abbreviated if the positions is
					// included in $format followed by "."
					$givn .= " " . $exp_givn [$i] {0} . ".";
				}
			}
		}
		
		
		
		
		// now replace unknown names with three dots
		$givn = str_replace ( array ('@P.N.','@N.N.' 
		), 
				array (I18N::translateContext ( 'Unknown given name', '…' ),
						I18N::translateContext ( 'Unknown surname', '…' ) 
				), trim ( $givn ) );
		
		return $givn;
	}
	
	/**
	 * Format a place
	 *
	 * Creates a string with a place where the format is defined by $format.
	 * Suppose the place is "street, town, county, country".
	 * if $format is not given the "street, town, county, country" is returned.
	 * if $format="1" then the "street" is returned.
	 * if $format="-1" then the "country" is returned.
	 * if $format="2,-1" then the "town, country" is returned.
	 * if $format="2,3,-1" then the "town, county, country" is returned.
	 * if $format="2/town,3,-1/another_country/third_country" then the "county, country" is returned.
	 * "/" is a separator which defines a list of names which are omitted.
	 *
	 * @param object $place
	 *        	A place object
	 * @param string $format
	 *        	The hierarchy levels to be returned.
	 * @return string
	 */
	private function formatPlace($place, $format) {
		$place_ret = "";
		// get the name of the place object
		if (is_object ( $place ) and
				 get_class ( $place ) == "Fisharebest\Webtrees\Place") {
			$place = $place->getGedcomName ();
		}
		if ($place) {
			// check if default format to be used
			if (!$format and array_key_exists("default_place_format",$_GET)) {
				$format = $_GET["default_place_format"];
			}
			
			if (! $format) {
				// use full place name if $format is not given
				$place_ret .= $place;
			} else {
				$format_place_level = explode ( ",", $format );
				$exp_place = explode ( ',', $place );
				$count_place = count ( $exp_place );
				
				// loop over format components
				foreach ( $format_place_level as $s ) {
					// check if there are names to be omitted seperated by "/"
					$sarray = explode ( "/", $s );
					$i = ( int ) $sarray [0];
					if (abs ( $i ) <= $count_place && $i != 0) {
						// the required hierarch level must exists
						if ($i > 0) {
							// hierarchy level counted from left
							$sp = trim ( $exp_place [$i - 1] );
						} else {
							// hierarchy level counted from right
							$sp = trim ( $exp_place [$count_place + $i] );
						}
						// check if name should be omitted
						if (in_array ( $sp, $sarray ))
							$sp = "";
							
							// add comma separator
						if ($place_ret != "" & $sp != "")
							$place_ret .= ", ";
						$place_ret .= $sp;
					}
				}
			}
		}
		return $place_ret;
	}
	
	/**
	 * Format a date
	 *
	 * This module takes a date object and returns the date formatted as
	 * defined by $format.
	 *
	 * @param Date $date        	
	 * @param string $format
	 *        	A standard PHP date format.
	 * @return string
	 */
	private function formatDate($date, $format) {
		if ($date instanceof Date) {
			// check if default format to be used
			if (!$format and array_key_exists("default_date_format",$_GET)) {
				$format = $_GET["default_date_format"];
			}
				
			$date_label = strip_tags ( $date->display ( false, $format ) );
		} else {
			$date_label = "";
		}
		return $date_label;
	}
	
	/**
	 * Write date to output file
	 *
	 * This module write data to the outfile and takes care
	 * of all required transformations.
	 *
	 * @param resource $gedout
	 *        	Handle to a writable stream
	 * @param string $buffer
	 *        	The string to be written in the file
	 */
	private function graphml_fwrite($gedout, $buffer) {
		fwrite ( $gedout, mb_convert_encoding ( $buffer, 'UTF-8' ) );
	}
	
	/**
	 * Returns the portrait file name
	 *
	 * This module returns the file name of the portrait of an individual.
	 *
	 * @param Individual $record
	 *        	The record of an idividual
	 * @param string $format
	 *        	If $format = "silhouette" then allways the fallback
	 *        	picture is used. If $format = "fallback" then the portrait is used and only
	 *        	if this is not defined the fallback picture is used.
	 * @param string $servername
	 *        	If $servername = true then the server file name
	 *        	including the path is returned.
	 * @return string The file name of the portrait
	 */
	private function getPortrait($record, $format, $servername = false) {
		$portrait_file = "";
		
		// get the fallback picture
		// the name is defined in the report form
		if ($format == "silhouette" || $format == "fallback") {
			$sex = $record->getSex ();
			if ($sex == "F") {
				$s = 'female';
			} elseif ($sex == "M") {
				$s = 'male';
			} else {
				$s = 'unknown';
			}
			if (array_key_exists ( 'default_portrait_' . $s, $_GET )) {
				$portrait_fallback = $_GET ['default_portrait_' . $s];
			} else {
				$portrait_fallback = "";
			}
		}
		
		if ($format == "silhouette") {
			// return the fallback figure if $format == "silhouette"
			$portrait_file = $portrait_fallback;
		} else {
			$portrait = $record->findHighlightedMedia ();
			if ($portrait) {
				if ($servername) {
					// get the full server name including path
					$portrait_file = $portrait->getServerFilename ();
				} else {
					// get the file name without full server path
					$portrait_file = $portrait->getFilename ();
				}
			}
			If ($format == "fallback" && $portrait_file == "")
				$portrait_file = $portrait_fallback;
		}
		
		return $portrait_file;
	}
	
	/**
	 * Get portrait size
	 *
	 * This module returns the height or width a portrait must have
	 * to preserve the aspect ratio given a pre-defined width or height.
	 * A width is defined when $format[0] starts with a "w" followed by the width.
	 * A height is defined when $format[0] starts with a "h" followed by the height.
	 *
	 * If $format is of length 2 then the second array element contains a default
	 * size. This is used for fallback figures.
	 *
	 * @param Individual $record        	
	 * @param array $format        	
	 * @return string
	 */
	private function getPortraitSize($record, $format) {
		// get portrait file
		$format_Size = $format [0];
		if (count ( $format ) > 1) {
			$format_default = $format [1];
		} else {
			$format_default = "";
		}
		
		$portrait_file = $this->getPortrait ( $record, "", true );
		$image_length = $format_default;
		
		if ($portrait_file != "" && strlen ( $format_Size ) > 1) {
			$constraint = $format_Size {0};
			$size_constraint = ( float ) substr ( $format_Size, 1 );
			$image_size = getimagesize ( $portrait_file );
			$width = $image_size [0];
			$height = $image_size [1];
			
			if ($constraint == "w") {
				// constraint is the width, get the height
				if ($width != 0)
					$image_length = ( int ) ($height * $size_constraint / $width);
			} else {
				// constraint is the height, get the width
				if ($height != 0)
					$image_length = ( int ) ($width * $size_constraint / $height);
			}
		}
		
		return $image_length;
	}
	
	/**
	 * Get facts
	 *
	 * This module return a list of facts for an individual or family. All facts are
	 * are of gedcom type defined by $fact. E.g. $fact = "OCCU" selects occupations.
	 * The $format parameter defines which facts are returned, e.g.
	 * $format=-1 means that the last fact with identifier $fact from the
	 * ordered fact list will be returned. Doublets in the fact list are removed
	 * automatically.
	 *
	 * @param Individual $record
	 *        	The record for which the facts are returned
	 * @param string $fact
	 *        	The gedcom identifier of the fact, e.g. "OCCU"
	 * @param string $format
	 *        	A list of positions in the ordered fact list which are returned
	 * @return string A comma separted list of facts
	 */
	private function getFact($record, $fact, $format = null) {
		// get all facts with identifier $fact as ordered array
		$fact_string = "";
		$Facts = $record->getFacts ( $fact, true );
		if ($Facts) {
			if (empty($format)) {
				// if $format is not given return all items
				foreach ( $Facts as $Fact ) {
					$fact_string .= $Fact->getValue ();
				}
			} else {
				// selects the items from the fact array as defined
				// in the $format parameter
				$exp_format = explode ( ",", $format );
				$count_facts = count ( $Facts );
				// fact list is used to avoid having facts twice
				$fact_list = array ();
				// loop over all components of $format
				foreach ( $exp_format as $s ) {
					$i = ( int ) $s;
					// check if item position exists
					if (abs ( $i ) <= $count_facts && $i != 0) {
						if ($i > 0) {
							$j = $i - 1;
						} else {
							// if position is negativ count from the end
							$j = $count_facts + $i;
						}
						$fact_value = trim ( $Facts [$j]->getValue () );
						if (! in_array ( $fact_value, $fact_list )) {
							// add a separator
							if ($fact_string != "")
								$fact_string .= ", ";
							$fact_list [] = $fact_value;
							$fact_string .= $fact_value;
						}
					}
				}
			}
		}
		
		return trim ( $fact_string );
	}
	
	/**
	 * Return the header for the graphml file
	 *
	 * This module returns the header of the graphml file
	 *
	 * @return String The header of the graphml file
	 */
	private function graphmlHeader() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . "\n" .
				 '<graphml xmlns="http://graphml.graphdrawing.org/xmlns" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:y="http://www.yworks.com/xml/graphml" xsi:schemaLocation="http://graphml.graphdrawing.org/xmlns http://www.yworks.com/xml/schema/graphml/1.1/ygraphml.xsd">' .
				 "\n" . '<!--Created by Webtrees -->' . "\n" .
				 '<key for="graphml" id="d0" yfiles.type="resources"/>' . "\n" .
				 '<key for="node" id="d1" attr.name="url" attr.type="string"/>' .
				 "\n" .
				 '<key for="node" id="d2" attr.name="description" attr.type="string"/>' .
				 "\n" . '<key for="node" id="d3" yfiles.type="nodegraphics"/>' .
				 "\n" .
				 '<key for="edge" id="d4" attr.name="url" attr.type="string"/>' .
				 "\n" .
				 '<key for="edge" id="d5" attr.name="description" attr.type="string"/>' .
				 "\n" . '<key for="edge" id="d6" yfiles.type="edgegraphics"/>' .
				 "\n" . "\n" . '<graph edgedefault="directed" id="G">' . "\n";
	}
	
	/**
	 * Increases the time limit
	 *
	 * This module estimates the processing time and 
	 * increases the php time limit accordingly 
	 *
	 * @return Boolean
	 */
	//static $time_counter = 0;
	//static $time_start = microtime(true);
	
	private function increaseTimeLimit() {
		//global  $time_counter, $time_start;
		static $time_counter = 0;
		static $time_start;
		
		//if (empty($counter)) $counter = 0;
		if (empty($time_start)) $time_start = microtime(true);
		
		$time_counter++;
		if ($time_counter == 500) {
			$time_med = (microtime(true) - $time_start) * 1000;
			
			set_time_limit (intval( $time_med) );
			
			$time_counter = 0;
			$time_start = microtime(true);
			
			return true;
		}
		return false;
		
	}
	
	/**
	 * Return the footer for the graphml file
	 *
	 * This module returns the footer of the graphml file
	 *
	 * @return String The footer of the graphml file
	 */
	private function graphmlFooter() {
		return '<data key="d0"> <y:Resources/> </data>' . "\n" .
				 '</graph> </graphml>';
	}
	
	/**
	 * Split template into components
	 *
	 * This module takes a template entered in the web form and converts it
	 * to a list stored in an array. The components of the list are
	 * either strings or tags with formats. Tags are supposed to be replaced
	 * by gedcom data during export.
	 *
	 * @param String $template
	 *        	The template to be decomposed.
	 * @return Array Each element of the array is itself an array.
	 *         Each of these arrays consist of 4 elements.
	 *         Element "type" defines if the template component is a string ("string")
	 *         or a tag ("tag"). The "component" contains either the string or the tag
	 *         name. "format" contains a format array and "fact" the gedcom fact identifier
	 *         in case the tag is "Fact".
	 */
	private function splitTemplate($template) {
		
		// check that the template has at least four characters
		if (strlen ( $template ) > 4) {
			// extract the symbols identifying tags and format descriptions
			$bracket_array = array ($template [0],$template [1] 
			);
			
			$tag = $template [2];
			$format = $template [3];
			
			// remove line breaks
			$template = trim ( preg_replace ( '/\s+/', ' ', $template ) );
			
			// start with an "{" to remove everything if no data are found
			$template_array = array (
					array ("component" => $bracket_array [0],"type" => 'string',
							"format" => "","fact" => "", "subtemplate" => "" 
					) 
			);
			
			$i = 1;
			$pos_end = 3;
			$pos = 0;
			
			// now split the template searching for the next tag symbol
			// $pos is the position of the next tag symbol
			// $pos_end is the position of the last tag
			while ( $pos !== false ) {
				$pos = strpos ( $template, $tag, $pos_end + 1 );
				if ($pos === false) {
					// no additional tag symbol found
					if ($pos_end + 1 < strlen ( $template )) {
						// there is a terminating string at the end of the template
						// add the string to the return array
						// substring {...} are removed
						$template_array [$i] = array (
								"component" => $this->removeBrackets ( 
										substr ( $template, $pos_end + 1 ), 
										$bracket_array ),"type" => "string",
								"format" => "","fact" => "", "subtemplate" => ""
						);
						$i ++;
					}
				} else {
					// there is an additional tag symbol
					
					if ($pos > $pos_end + 1) {
						// there is a string preceeding the tag symbol
						// add the string to the return array
						// substring {...} are removed
						$template_array [$i] = array (
								"component" => $this->removeBrackets ( 
										substr ( $template, $pos_end + 1, 
												$pos - $pos_end - 1 ), 
										$bracket_array ),"type" => "string",
								"format" => "","fact" => "", "subtemplate" => ""
						);
						$i ++;
					}
					
					// now the tag is added to the return array
					// search for the end of the tag
					$pos_end = strpos ( $template, $tag, $pos + 1 );
					
					// check for a format string
					$pos_format = strpos ( $template, $format, $pos );
						
					if ($pos_format < $pos_end && $pos_format !== false) {
						// a format definition exists, split it
						$format_array = explode ( $format,
								substr ( $template, $pos_format + 1,
										$pos_end - $pos_format - 1 ) );
					} else {
						$format_array = array ("");
						$pos_format = $pos_end;
					}
					$component = substr ($template,
							$pos + 1, $pos_format - $pos - 1);
						
					
					if ($pos_end !== false) {
						if (substr ( $template, $pos, 8 ) == "@Foreach") {
							// this a a foreach loop
							// search the end of the loop
							$pos_EndForeach = null;
							$pos_foreach = strpos($template,"@Foreach",$pos + 1);
							$pos_fend = strpos($template,"@EndForeach@",$pos + 1);
							$count_foreach = 1;
								
							do  {
								if (!$pos_foreach or $pos_foreach > $pos_fend){
									// EndForeach is next
									$count_foreach -= 1;
									if ($count_foreach == 0) {
										// EndForeach belongs to first Foreach
										$pos_EndForeach = $pos_fend;
									} else {
										// search for next pos_end
										$pos_fend = strpos($template,"@EndForeach@",$pos_fend + 1);
									}
								} else {
									// Foreach is next
									$pos_foreach = strpos($template,"@Foreach",$pos_foreach + 1);
									$count_foreach += 1;
								}
							} while(is_null($pos_EndForeach)); 
								
							if (!is_null($pos_EndForeach)) {
								$subTemplate = substr ( $template, 0, 4 ) .
										 substr ( $template, $pos_end + 1, 
												$pos_EndForeach - $pos_end - 1 );
								// add the tag to the return array
								$subTemplateSplit = $this->splitTemplate ( 
										$subTemplate );
								$template_array [$i] = array (
										"component" => $component,
										"type" => "foreach", 
										"format" => $format_array,
										"fact" => "",
										"subtemplate" => $subTemplateSplit
								);
								// set position end to the end of @EndForeach@
								$pos_end = $pos_EndForeach + 11;
								$i ++;
							}
						} else {
							// this is a single tag
							// get the format definition
							//$pos_format = strpos ( $template, $format, $pos );
							$template_array [$i] = array (
									"component" => $component,
									"type" => "tag",
									"format" => $format_array,
									"fact" => "",
									"subtemplate" => ""
							);
							$i ++;								
						}
					}
				}
			}
			
			// end with an "}" matching the "{" at the beginning
			$template_array [$i] = array ("component" => $bracket_array [1],
					"type" => 'string',"format" => '',"fact" => '', "subtemplate" => "" 
			);
			
			// now search for tags defining facts and filling the
			// "fact" array element
			for($j = 0; $j < $i; $j ++) {
				if ($template_array [$j] ["type"] == "tag" and substr ( $template_array [$j] ["component"], 0, 4 ) == "Fact") {
					$template_array [$j] ["fact"] = substr ( 
							$template_array [$j] ["component"], 4 );
					$template_array [$j] ["component"] = "Fact";
				}
			}
		} else {
			if (strlen ( $template ) > 1) {
				// extract the symbols identifying tags and format descriptions
				$bracket_array = array ($template [0],$template [1]);
			} else {
				$bracket_array = array();
			}
			$template_array = array();
		}
		
		return array ($template_array, $bracket_array 
		);
	}
	
	/**
	 * Remove brackets
	 *
	 * This module removes substring {...} within a string.
	 *
	 * @param string $subject
	 *        	the input string where brackets should be
	 *        	removed.
	 * @return string The input string where brackets are removed.
	 */
	private function removeBrackets($subject, $brackets) {
		$count = 1;
		// take into account that there might be multiple brackets.
		if (! is_null ( $brackets )) {
			while ( $count > 0 ) {
				// use regular expressions to remove brackets
				$subject = preg_replace ( 
						"/\Q" . $brackets [0] . "\E[^\Q" . $brackets [0] .
								 $brackets [1] . "\E]*\Q" . $brackets [1] . "\E/", 
								"", $subject, - 1, $count );
			}
		}
		return $subject;
	}
	/**
	 * Get the value of level 2 data in the fact
	 *
	 * @param fact $fact
	 * @param string $tag
	 *
	 * @return string|null
	 */
	private function getAllAttributes($fact, $tag) {
		$gedcom = $fact->getGedcom();
		if (preg_match_all('/\n2 (?:' . $tag . ') ?(.*(?:(?:\n3 CONT ?.*)*)*)/', $gedcom, $match)) {
			return preg_replace("/\n3 CONT ?/", "\n", $match[1]);
		} else {
			return null;
		}
	}
	
	/**
	 * Substitute place holder in template for individuals
	 *
	 * This module substututes place holders in templates for individuals.
	 *
	 * @param GedcomRecord $record
	 *        	The record for which the template should be filled (Individual, Family,..)
	 * @param GedcomRecord $record_context
	 *        	The context record, e.g. the individual for which the FAMS should be taken.
	 * @param string $template
	 *        	The template with place holders to be replaced
	 * @param array $brackets
	 *        	Left and right brackets to define tag block
	 * @param string $symbols
	 *        	List of symbols to the used for facts
	 * @param integer $counter
	 *        	Counter of foreach loop
	 * @param string $fact_type
	 *        	Fact type within a foreach fact loop
	 * @return string The template with place holders replaced
	 */
	private function substitutePlaceHolder($record, $record_context, $template, $doctype, 
			$brackets = array("{","}"), $fact_symbols = array(), $counter = 0, $fact_type = "") {
		global $generationFam, $generationInd;
		//$xref = $record->getXref();
		//if (!empty($xref) and $xref[0] == "I") {
		if ($record instanceof Fisharebest\Webtrees\Individual ) {
			$FAMC = $record->getChildFamilies ();
		}
		/*
		 * replace tags in the template with data
		 *
		 * Algorithm:
		 * - loop over all template components
		 * - unless no tag with data is found concatenate all strings
		 * and store it in $new_string
		 * - if a tag for which gedcom data exist is found then
		 * remove all brackets {...} in $new_string and add $new_string
		 * and the "tag data" to the string $nodetext[$a]. Then set
		 * $new_string ="".
		 */
		$nodetext = '';
		$new_string = '';
		if ($template) {
			// loop over all template components
			foreach ( $template as $comp ) {
				$tag_replacement = "";
				$tag_found = FALSE;
				
				if ($comp ["type"] == "string") {
					// element is a string, add it to $new_string
					$new_string .= $comp ["component"];
					// $new_string .= $this->substituteSpecialCharacters (
					// $comp ["component"], $doctype);
				} elseif ($comp ["type"] == "tag") {
					// element is a tag, get the tag data
					$format = $comp ["format"];
					switch ($comp ["component"]) {
						case "GivenName" :
						case "FatherGivenName" :
						case "MotherGivenName" :
						case "SpouseGivenName" :
						case "SurName" :
						case "FatherSurName" :
						case "MotherSurName" :
						case "SpouseSurName" :
							$rec = null;
							switch ($comp ["component"]) {
								case "GivenName":
								case "SurName":
									$rec = $record;
								break;
								case "FatherGivenName":
								case "FatherSurName" :
									if (count ( $FAMC ) > 0) {
										$rec = $FAMC [0]->getHusband ();
									}
								break;
								case "MotherGivenName":
								case "MotherSurName":
									if (count ( $FAMC ) > 0) {
										$rec = $FAMC [0]->getWife ();
									}
								break;
								case "SpouseGivenName":
								case "SpouseSurName":
									$rec = $record->getHusband ();
									if (is_null($rec) or $record_context->getXref() == $rec->getXref()) {
										$rec = $record->getWife ();
									}
								break;
								}
							switch ($comp ["component"]) {
								case "GivenName" :
								case "FatherGivenName" :
								case "MotherGivenName" :
								case "SpouseGivenName" :
									If (! is_null ( $rec )) {
									$tag_replacement .= $this->getGivenName ( $rec, 
										$format [0] );
									}							
									break;
								case "SurName" :
								case "FatherSurName" :
								case "MotherSurName" :
								case "SpouseSurName" :
									If (! is_null ( $rec )) {
										$tag_replacement .= $rec->getAllNames () [$rec->getPrimaryName ()] ['surname'];
										$tag_replacement = str_replace ( '@N.N.', 
										I18N::translateContext ( 
												'Unknown surname', '…' ), 
										$tag_replacement );
									}
									break;		
							}
								
							$tag_replacement = $this->substituteSpecialCharacters($tag_replacement, $doctype );
							break;
						case "NickName" :
							$rec_name = $record->getFacts ("NAME", true)[0];
							$nickname = $rec_name->getAttribute("NICK");
							if ($nickname) {
								$tag_replacement .= $this->substituteSpecialCharacters($nickname, $doctype );
							}
							break;
						case "Id" :
						case "FatherId" :
						case "MotherId" :
							$xref = null;
							if ($comp ["component"] == "Id") {
								$xref = $record->getXref ();
							} elseif (count ( $FAMC ) > 0) {
								if ($comp ["component"] == "FatherId") {
									$rec = $FAMC [0]->getHusband ();
								} elseif ($comp ["component"] == "MotherId") {
									$rec = $FAMC [0]->getWife ();
								}
								If (! is_null ( $rec )) {
									$xref = $rec->getXref ();
								}
							}
							If (! is_null ( $xref )) {
								if ($format [0] == "no") {
									$tag_replacement .= $generationInd [$xref] ["no"];
								} elseif ($format [0] == "gen_no") {
									$tag_replacement .= $generationInd [$xref] ["gen_no"];
								} else {
									$tag_replacement .= xref;
								}
							}
							$tag_replacement = $this->substituteSpecialCharacters($tag_replacement, $doctype );
							break;
						case "BirthDate" :
							$tag_replacement .= $this->formatDate ( 
									$record->getBirthDate (), $format [0] );
							$tag_replacement = $this->substituteSpecialCharacters($tag_replacement, $doctype );
							break;
						case "BirthPlace" :
							$tag_replacement .= $this->formatPlace ( 
									$record->getBirthPlace (), $format [0] );
							$tag_replacement = $this->substituteSpecialCharacters($tag_replacement, $doctype );
							break;
						case "DeathDate" :
							$tag_replacement .= $this->formatDate ( 
									$record->getDeathDate (), $format [0] );
							$tag_replacement = $this->substituteSpecialCharacters($tag_replacement, $doctype );
							break;
						case "DeathPlace" :
							$tag_replacement .= $this->formatPlace ( 
									$record->getDeathPlace (), $format [0] );
							$tag_replacement = $this->substituteSpecialCharacters($tag_replacement, $doctype );
							break;
						case "MarriageDate" :
							$tag_replacement .= $this->formatDate ( 
									$record->getMarriageDate (), $format [0] );
							$tag_replacement = $this->substituteSpecialCharacters($tag_replacement, $doctype );
							break;
						case "MarriagePlace" :
							$tag_replacement .= $this->formatPlace ( 
									$record->getMarriagePlace (), $format [0] );
							$tag_replacement = $this->substituteSpecialCharacters($tag_replacement, $doctype );
							break;
						case "Fact" :
							$tag_replacement .= $this->getFact ( $record, 
									$comp ["fact"], $format [0] );
							$tag_replacement = $this->substituteSpecialCharacters($tag_replacement, $doctype );
							break;
						case "FeAttributeType" :
						case "FeFactType" :
							$return_value = TRUE;
							if ($format[0] == "IfExist" and  get_class ( $record ) != "Fisharebest\Webtrees\Fact") {
								$fact_records = $record->getFacts ( $fact_type, true );
								if (empty($fact_records)) {
									$return_value = FALSE;
								}
							}
							if ($return_value) {
								if (!empty($fact_symbols) and array_key_exists($fact_type, $fact_symbols)) {
									$tag_replacement .= $fact_symbols[$fact_type];
								} else {
									$tag_replacement .= $this->substituteSpecialCharacters(
											GedcomTag::getLabel($fact_type, $record_context)
											, $doctype );
									$tag_replacement = $this->substituteSpecialCharacters($tag_replacement, $doctype );
									
									if (count($format) > 1) {
										$tag_replacement = $format[1] . $tag_replacement;
									}
									if (count($format) > 2) {
										$tag_replacement = $tag_replacement . $format[2];
									}
								}
								$new_string .= $tag_replacement;
								$tag_replacement = '';
							}
							break;
						case "FeFactValue" :
							$tag_replacement .= $record->getValue();
							$tag_replacement = $this->substituteSpecialCharacters($tag_replacement, $doctype );
							break;
						case "FeFactDate" :
							$tag_replacement .= $this->formatDate ( $record->getDate(),$format [0]) ;
							$tag_replacement = $this->substituteSpecialCharacters($tag_replacement, $doctype );
							break;
						case "FeFactPlace" :
							$tag_replacement .= $this->formatPlace ( $record->getPlace(),$format [0]);
							$tag_replacement = $this->substituteSpecialCharacters($tag_replacement, $doctype );
							break;	
						case "FeFactAttribute" :
							if ($fact_type == 'DATE') {
								$tag_replacement .= $this->formatDate ( $record->getDate(),$format [0]) ;
							} else if ($fact_type == 'PLAC') {
								$tag_replacement .= $this->formatPlace ( $record->getPlace(),$format [0]);
							} else {
								$tag_replacement .= $record->getAttribute($fact_type);
							}
							$tag_replacement = $this->substituteSpecialCharacters($tag_replacement, $doctype );
							break;	
						case "FeFactNote" :
							$notes = $record->getNotes();
							foreach ($notes as $n) {
								$tag_replacement .= $this->substituteSpecialCharacters($n, $doctype ) . '\\ ';
							}
							break;	
						case "FeMediaFile" :
							$filename = $record->getFilename();
							$filename_array = explode(".",$record->getFilename());
							$n = count($filename_array);
							if ($n == 1) {
								// file name has no ending stop code
								exit ('Image name \'' . $filename . "\' does not have an ending" );
							} else if ($format[0] == "NoExtension") {
								// return file name without ending	 
									$tag_replacement .= implode(".", array_slice($filename_array,0,$n-1));
							} else {
								// return full file name
								$tag_replacement .= $filename;
							} 
							//$tag_replacement = $this->substituteSpecialCharacters($tag_replacement, $doctype );
							break;	
						case "FeMediaCaption" :
							$tag_replacement .= $record->getTitle();
							$tag_replacement = $this->substituteSpecialCharacters($tag_replacement, $doctype );
							break;	
						case "FeReferenceName" :
							$tag_replacement .= str_replace('@', '',str_replace('S', '', $record));
							$tag_replacement = $this->substituteSpecialCharacters($tag_replacement, $doctype );
							break;	
						case "Portrait" :
							$tag_replacement .= $this->getPortrait ( $record, 
									$format [0] );
							$tag_replacement = $this->substituteSpecialCharacters($tag_replacement, $doctype );
							break;
						case "PortraitSize" :
							$tag_replacement = $this->getPortraitSize ( 
									$record, $format );
							break;
						case "Counter" :
							if ($counter > 0) $tag_replacement = $counter;
							break;
						case "Gedcom" :
							$tag_replacement = preg_replace ( "/\\n/", "<br>", 
									$record->getGedcom () );
							$tag_replacement = $this->substituteSpecialCharacters($tag_replacement, $doctype );
							break;
						case "Marriage" :
							$marriage = $record->getMarriage ();
							if ($marriage) {
								$tag_found = TRUE;
							}
							break;
						case "MarriageDate" :
							$tag_replacement .= $this->formatDate ( 
									$record->getMarriageDate (), $format [0] );
							$tag_replacement = $this->substituteSpecialCharacters($tag_replacement, $doctype );
							break;
						case "MarriagePlace" :
							// $record->getMarriagePlace() does not work because
							// there is no exception handling in the function
							$marriage = $record->getMarriage ();
							if ($marriage) {
								$tag_replacement .= $this->formatPlace ( 
										$marriage->getPlace (), $format [0] ) ;
								$tag_replacement = $this->substituteSpecialCharacters($tag_replacement, $doctype );
							}
							break;
						case "Remove" :
							$search_array = array();
							$replace_array = array();
							foreach ($format as $f) {
								$search_array[] = "/\Q" . $f . '\E(?=[\\r\\n\\' .$brackets [0] . '\\' . $brackets [1] . '\s]*$)/';
								$replace_array[] = '';
							};
							if (str_replace(array("\n"," "),"",$new_string) != '') {
								$new_string = $this->removeBrackets ( 
										$new_string, $brackets );
								do {
									$old_length = strlen($new_string);
									$new_string = preg_replace ( $search_array, $replace_array, $new_string );
									$new_length = strlen($new_string);
								} while($old_length !=  $new_length);
								
							} else {
								do {
									$old_length = strlen($nodetext);
									$nodetext = preg_replace ( $search_array, $replace_array, $nodetext );
									$new_length = strlen($nodetext);
								} while($old_length !=  $new_length);
							}
						break;
						case "Replace" :
							if (count($format) > 1 ){
								$search_array = "/\Q" . $format[0] . '\E(?=[\\r\\n\\' . $brackets [0] . '\\' . $brackets [1] . '\s]*$)/';
								$replace_array = str_replace ('\\','\\\\',$format[1]);
								if (str_replace(array("\n"," "),"",$new_string) != '') {
									$new_string = $this->removeBrackets (
											$new_string, $brackets );
									do {
										$old_length = strlen($new_string);
										$new_string = preg_replace ( $search_array, $replace_array, $new_string );
										$new_length = strlen($new_string);
									} while($old_length !=  $new_length);
							
								} else {
									do {
										$old_length = strlen($nodetext);
										$nodetext = preg_replace ( $search_array, $replace_array, $nodetext );
										$new_length = strlen($nodetext);
									} while($old_length !=  $new_length);
								}
							}
							break;
						
					}

				} elseif ($comp ["type"] == "foreach") {
					// loop
					
					$counter = 0;
					switch ($comp ["component"]) {
						case "ForeachFAMS" :
							// loop over all spouse families of an individual
							$FAMS = $record->getSpouseFamilies ();
							foreach ( $FAMS as $family ) {
								$counter += 1;
								$tag_replacement .= $this->substitutePlaceHolder($family, $record, $comp ["subtemplate"][0], $doctype, $comp ["subtemplate"][1], array(), $counter);
							}	
						break;
						case "ForeachChildren" :
							// loop over all children of a family 
							$children = $record->getChildren ();
							foreach ( $children as $child) {
								$counter += 1;
								$tag_replacement .= $this->substitutePlaceHolder($child, $record, $comp ["subtemplate"][0], $doctype, $comp ["subtemplate"][1], array(), $counter);
							}	
						break;
						case "ForeachFactOuter" :
						case "ForeachAttributeOuter" :
							// loop over all fact types
							foreach (explode ( ',', $comp["format"] [0]) as $fact_type ) {
								// loop over facts of the same type sorted
								$counter += 1;
								$tag_replacement .= $this->substitutePlaceHolder($record, 
												$record_context, $comp ["subtemplate"][0], $doctype, 
												$comp ["subtemplate"][1], $fact_symbols, $counter, $fact_type);
							}
						break;
						case "ForeachFactInner" :
							// loop over facts of the same type sorted
							$fact_records = $record->getFacts ( $fact_type, true );
							if (!empty($fact_records)) {
								foreach ($fact_records as $fact_record) {
									$counter += 1;
									$tag_replacement .= $this->substitutePlaceHolder($fact_record, 
											$record, $comp ["subtemplate"][0], $doctype, 
											$comp ["subtemplate"][1], $fact_symbols, $counter, $fact_type);
								}
							}
						break;
						case "ForeachMedia" :
							// loop over media
							$media_links = $record->getFacts ( "OBJE", true );
							if (!empty($media_links)) {
								foreach ($media_links as $media_link) {
									$id = str_replace("@","",$media_link->getValue());

									$media_record = Media::getInstance($id, $record_context->getTree());
									$use = true;
									
									// check if type is in format[0]
									if ($comp["format"] [0] != "") {
										$types = explode ( ',', $comp["format"] [0]);
										$type = $media_record->getMediaType();
										if (!in_array($type, $types)) {
											$use = false;
										} 
									}
									
									// check if ending is in format[1]
									if ($comp["format"] [1] != "") {
										$endings = array_map("strtolower",explode ( ',', $comp["format"] [1]));
										
										$filename_array = explode(".",$media_record->getFilename());
										$n = count($filename_array);
										if ($n > 1) {
											$ending = strtolower($filename_array[$n-1]);
											if (!in_array($ending, $endings)) {
												$use = false;
											} 
										} else {
											$use = false;
										}										
									}
									if ($use and $media_record->canShow()) {
										$counter += 1;
										$tag_replacement .= $this->substitutePlaceHolder($media_record, 
											$record_context, $comp ["subtemplate"][0], $doctype, 
											$comp ["subtemplate"][1], $fact_symbols, $counter, $fact_type);
									}
								}
							}
						break;
						case "ForeachReference" :
							// get direct sources
							$facts = $record->getFacts('SOUR');
							$all_sources = array();
							foreach ($facts as $fact) {
								$all_sources[] = $fact->getValue();
							}
							
							// get all facts
							$facts = $record->getFacts();
							foreach ($record->getSpouseFamilies() as $family) {
								if ($family->canShow()) {
									foreach ($family->getFacts() as $fact) {
										$facts[] = $fact;
									}
								}
							}

							// get sources from facts
							foreach ($facts as $fact) {								
								$fact_sources = $this->getAllAttributes($fact,'SOUR');
								if ($fact_sources) {
									$all_sources = array_merge($all_sources, $fact_sources);
								}
							}
							$all_sources = array_unique($all_sources);
							
							foreach ($all_sources as $source){
									$counter += 1;
									$tag_replacement .= $this->substitutePlaceHolder($source, 
											$record, $comp ["subtemplate"][0], $doctype, 
											$comp ["subtemplate"][1], $fact_symbols, $counter, $fact_type);
							}
						break;
					}						
				}
				//
				if ($tag_replacement != "" or $tag_found) {
					// data for the tag exists
					// check for a {...} in $new_string and remove it
					$new_string = $this->removeBrackets ( $new_string,
							$brackets );
					// add $new_string to $nodetext[$a]
					$nodetext .= $new_string . $tag_replacement;
					$new_string = '';
				}
			}
		}
		
		// add remaining strings to $nodetext
		$new_string = $this->removeBrackets ( $new_string, $brackets );
		$nodetext .= $new_string;
		// remove all remaining brackets (which contain record data)
		$nodetext = preg_replace ( 
				array ("/\Q" . $brackets [0] . "\E/",
						"/\Q" . $brackets [1] . "\E/" 
				), array ("","" 
				), $nodetext );
		
		if ($doctype == "graphml") {
			$nodetext = preg_replace ( "/<html>\s*<\/html>/", "", $nodetext );
		}
		
		return $nodetext;
	}
	
	/**
	 * Substitute characters
	 *
	 * This module substitutes the following special
	 * html characters:
	 * " -> &quot;
	 * & -> &amp;
	 * < -> &lt;
	 * > -> &gt;
	 * ' -> &apos;
	 * &nbsp; -> " "
	 *
	 * @param string $subject
	 *        	the input string where special characters should
	 *        	be substituted.
	 * @param string $doctype
	 *        	defines if the document is graphml or latex
	 * @return string The input string with substituted characters
	 */
	private function substituteSpecialCharacters($subject, $doctype) {
		if ($doctype == "graphml") {
			$replacements = array (array ("/&/","&amp;" 
			),array ("/\"/","&quot;" 
			),array ("/\'/","&apos;" 
			),array ("/(?<!br)>/","&gt;" 
			),array ("/<(?!br>)/","&lt;" 
			) 
			);
			/*
			 * // $subject = preg_replace ( '/&nbsp;/', ' ', $subject );
			 * $subject = preg_replace ( "/&/", "&amp;", $subject );
			 * // $subject = preg_replace ( "/&(?![^ ][^&]*;)/", "&amp;", $subject);
			 * $subject = preg_replace ( "/\"/", "&quot;", $subject );
			 * $subject = preg_replace ( "/\'/", "&apos;", $subject );
			 * $subject = preg_replace ( "/(?<!br)>/", "&gt;", $subject );
			 * $subject = preg_replace ( "/<(?!br>)/", "&lt;", $subject );
			 */
		}
		if ($doctype == "latex") {
			$replacements = array (array ('/\Q\\\E/','\textbackslash ' 
			),array ('/\Q&\E/','\& ' 
			),array ('/\Q$\E/','\$ ' 
			),array ('/\Q%\E/','\% ' 
			),array ('/\Q_\E/','\_ ' 
			),array ('/\Q^\E/','\^ ' 
			),array ('/\Q...\E/','\ldots ' 
			),array ('/\Q|\E/','\textbar ' 
			),array ('/\Q#\E/','\#' 
			),array ('/\Q§\E/','\S' 
			),array ('/\n/','\\ ' 
			)/*,array ("/\Qö\E/",'\"o' 
			),array ("/\Qä\E/",'\"a' 
			),array ("/\Qü\E/",'\"u' 
			),array ("/\QÖ\E/",'\"O' 
			),array ("/\QÄ\E/",'\"A' 
			),array ("/\QÜ\E/",'\"U' 
			)*/ 
			);
		}

		foreach ( $replacements as $r ) {
			$subject = preg_replace ( $r [0], $r [1], $subject );	
		}
		
		return $subject;
	}
	/**
	 * get Records for individuals and families
	 *
	 * @param Tree $tree
	 *        	The family tree to be considered
	 * @return string with all references
	 */
	private function getReferences($tree) {
		
		$ret_string = "\n" . '\usepackage{filecontents}
						\begin{filecontents}{\jobname.bib}';
		
		$source_rows = Database::prepare(
				"SELECT s_id,s_gedcom FROM `##sources` WHERE s_file = :tree_id"
				)->execute(array(
						'tree_id' => $tree->getTreeId (),
				))->fetchAll();
				// fetchOne()
				
		foreach ($source_rows as $rows) {
			$id = $rows->s_id;
			$ret_string .= "\n" . '@book{' . str_replace('S', '', $id);
			$record = GedcomRecord::getInstance ( $id, $tree );

			// title
			foreach ($record->getFacts('TITL') as $fact) {
				$ret_string .= ',title   ="' . $fact->getValue() . '"';
			}
			// author
			foreach ($record->getFacts('AUTH') as $fact) {
				$ret_string .= ',author   ="' . 
					str_replace(array(',','&'), array(' and ','\&'), $fact->getValue()) . '"';
			}
			$ret_string .= "}";
		}
		
		$ret_string .= "\n" . '\end{filecontents}' . "\n";
		
		return $ret_string;
	}
	
	/**
	 * get Records for individuals and families
	 *
	 * @param Tree $tree
	 *        	The family tree to be considered
	 * @param Boolean $sort
	 *        	determines if indiviuals are sorted by generation
	 * @return list record list for individuals and families
	 */
	private function getRecords($tree, $sort = FALSE) {
		// Get all individuals
		$rowsInd = Database::prepare ( 
				"SELECT i_id AS xref" .
						 " FROM `##individuals` WHERE i_file = :tree_id ORDER BY i_id" )->execute ( 
				array ('tree_id' => $tree->getTreeId () 
				) )->fetchAll ();
		
		// Get all family records
		$rowsFam = Database::prepare ( 
				"SELECT f_id AS xref" .
						 " FROM `##families` WHERE f_file = :tree_id ORDER BY f_id" )->execute ( 
				array ('tree_id' => $tree->getTreeId () 
				) )->fetchAll ();
		
		foreach ( $rowsInd as $row ) {
			$xrefInd [] = $row->xref;
		}
		;
		foreach ( $rowsFam as $row ) {
			$xrefFam [] = $row->xref;
		}
		;
		//
		global $generationFam, $generationInd;
		
		// now sort individuals
		function setFAMSFAMC($tree, $inds, $branch, $generation) {
			global $generationFam, $generationInd;
			foreach ( $inds as $ind ) {
				global $generationInd;
				if ($ind !== null) {
					// set branch and generation
					$xrefInd = $ind->getXref ();
					if (empty ( $generationInd ) or
							 ! array_key_exists ( $xrefInd, $generationInd )) {
						$record = Individual::getInstance ( $xrefInd, $tree );
						$name = $record->getAllNames () [$record->getPrimaryName ()] ['surname'];
						$name = str_replace ( '@N.N.', 
								I18N::translateContext ( 'Unknown surname', 
										'…' ), $name );
						
						$generationInd [$xrefInd] = array ("branch" => $branch,
								"generation" => $generation,"name" => $name,
								"givenname" => ExportGraphmlModule::getGivenName ( 
										$record ) 
						);
						
						// Familes where ind is a child FAMC, i.e. generation -1
						$FAMC = $ind->getChildFamilies ();
						foreach ( $FAMC as $family ) {
							setGeneration ( $tree, $family->getXref (), 
									$branch, $generation - 1 );
						}
						;
						// families of the same generation FAMS, i.e. generation +0
						$FAMS = $ind->getSpouseFamilies ();
						foreach ( $FAMS as $family ) {
							setGeneration ( $tree, $family->getXref (), 
									$branch, $generation );
						}
						;
					}
				}
			}
		}
		function setGeneration($tree, $xrefFam, $branch, $generation) {
			global $generationFam;
			
			// set generation for family
			if (empty ( $generationFam ) or
					 ! array_key_exists ( $xrefFam, $generationFam )) {
				// set generation and branch for family
				$generationFam [$xrefFam] = array ("branch" => $branch,
						"generation" => $generation,"no" => 0,"gen_no" => "" 
				);
				// get parents and children
				$record = Family::getInstance ( $xrefFam, $tree );
				$parents = array ($record->getHusband (),$record->getWife () 
				);
				$children = $record->getChildren ();
				setFAMSFAMC ( $tree, $parents, $branch, $generation );
				setFAMSFAMC ( $tree, $children, $branch, $generation + 1 );
			}
		}
		// loop over families to start with a new branch
		if ($sort) {
			# if reference individual are set start 
			# with this individual and return pnly one tree
			#
			$branch = 1;
			if ($_GET["refid"]) {
				$xrefInd = $_GET["refid"];
				$record = Individual::getInstance ( $xrefInd, $tree );
				$FAMS = $record->getSpouseFamilies ();
				
				if ($FAMS) {
					$xref = $FAMS[0]->getXref();
					setGeneration ( $tree, $xref, $branch, 1 );
					$branch += 1;
				}
			}
				
			foreach ( $xrefFam as $xref ) {
				if (empty ( $generationFam ) or
						 ! array_key_exists ( $xref, $generationFam )) {
					setGeneration ( $tree, $xref, $branch, 1 );
					$branch += 1;
				}
					;
			}
				;
			// now sort for branches and generations
			
			foreach ( $generationInd as $key => $row ) {
				$a_branch [$key] = $row ['branch'];
				$a_generation [$key] = $row ['generation'];
				$a_name [$key] = $row ['name'];
				$a_givenname [$key] = $row ['givenname'];
			}
			
			array_multisort ( $a_branch, SORT_ASC, $a_generation, SORT_ASC, 
					$a_name, SORT_ASC, $a_givenname, SORT_ASC, SORT_STRING, 
					$generationInd );
			
			// now et ids
			$no = 0;
			$gen_no = 0;
			$last_gen = array_values ( $generationInd ) [0] ['generation'];
			$first_gen = $last_gen;
			foreach ( $generationInd as $key => $row ) {
				if ($row ['generation'] > $last_gen) {
					$last_gen = $row ['generation'];
					$gen_no = 0;
				}
				$no += 1;
				$gen_no += 1;
				$generationInd [$key] ['no'] = $no;
				$generationInd [$key] ['gen_no'] = (1 + $last_gen - $first_gen) .
						 "." . $gen_no;
			}
		}
		
		//
		
		return array ($xrefInd, $xrefFam, $generationInd, $generationFam 
		);
	}
	
	/**
	 * Export the data in graphml format
	 *
	 * This is the main module which export the familty tree in graphml
	 * format.
	 *
	 * @param Tree $tree
	 *        	Which tree to export
	 * @param resource $gedout
	 *        	Handle to a writable stream
	 */
	private function exportGraphml(Tree $tree, $gedout) {
		
		// get parameter entered in the web form
		$parameter = $_GET;
		
		foreach ( array ("label","description" 
		) as $a ) {
			$name = "individuals_" . $a . "_template";
			// First split the html templates
			// This is done once and later used when exporting
			// data for the familty tree record.
			list ( $template [$a], $brackets [$a] ) = $this->splitTemplate ( 
					$parameter [$name] );
		}
		// Get header.
		// Buffer the output. Lots of small fwrite() calls can be very
		// slow when writing large files (copied from one of the webtree modules).
		$buffer = $this->graphmlHeader ();
		
		// get record of individuals and families
		list ( $xrefInd, $xrefFam ) = $this->getRecords ( $tree );
		
		/*
		 * Create nodes for individuals and families
		 */
		
		// loop over all individuals
		foreach ( $xrefInd as $xref ) {
			// increase time limit
			$this->increaseTimeLimit();
				
			$record = Individual::getInstance ( $xref, $tree );
			
			// get parameter for the export
			$sex = $record->getSex ();
			if ($sex == "F") {
				$s = 'female';
			} elseif ($sex == "M") {
				$s = 'male';
			} else {
				$s = 'unknown';
			}
			$col = $parameter ['color_' . $s];
			$node_style = $parameter ['node_style_' . $s];
			$col_border = $parameter ['border_' . $s];
			$box_width = $parameter ['box_width_' . $s];
			$border_width = $parameter ['border_width_' . $s];
			$font_size = $parameter ['font_size_' . $s];
			
			// loop to create the output for the node label
			// and the node description
			foreach ( array ("label","description" 
			) as $a ) {
				$nodetext [$a] = $this->substitutePlaceHolder ( $record, $record,
						$template [$a], "graphml", $brackets [$a] );
			}
			
			// the replacement of < and > has to be done for "lable"
			// for "description" no replacement must be done (not clear why)
			$nodetext ["label"] = str_replace ( "<", "&lt;", 
					$nodetext ["label"] );
			$nodetext ["label"] = str_replace ( ">", "&gt;", 
					$nodetext ["label"] );
			
			// count the number of rows to set the box height accordingly
			$label_rows = count ( 
					explode ( "&lt;br&gt;", $nodetext ["label"] ) ) +
					 count ( explode ( "&lt;tr&gt;", $nodetext ["label"] ) ) + 1;
			
			// create export for the node
			$buffer .= '<node id="' . $xref . '">' . "\n" .
					 '<data key="d1"><![CDATA[http://my.site.com/' . $xref .
					 '.html]]></data>' . "\n" . '<data key="d2"><![CDATA[' .
					 $nodetext ["description"] . ']]></data>' . "\n" .
					 '<data key="d3">' . '<y:GenericNode configuration="' .
					 $node_style . '"> <y:Geometry height="' . (12 * $label_rows) .
					 '" width="' . $box_width .
					 '" x="10" y="10"/> <y:Fill color="' . $col .
					 '" transparent="false"/> <y:BorderStyle color="' .
					 $col_border . '" type="line" width="' . $border_width .
					 '"/> <y:NodeLabel alignment="center" autoSizePolicy="content" hasBackgroundColor="false" hasLineColor="false" textColor="#000000" fontFamily="' .
					 $parameter ['font'] . '" fontSize="' . $font_size .
					 '" fontStyle="plain" visible="true" modelName="internal" modelPosition="l" width="129" height="19" x="1" y="1">';
			
			// no line break before $nodetext allowed
			$buffer .= $nodetext ["label"] . "\n" .
					 '</y:NodeLabel> </y:GenericNode> </data>' . "\n" .
					 "</node>\n";
			
			// write to file if buffer is full
			if (strlen ( $buffer ) > 65536) {
				$this->graphml_fwrite ( $gedout, $buffer );
				$buffer = '';
			}
		}
		
		/*
		 * Create nodes for families
		 */
		foreach ( array ("label","description" 
		) as $a ) {
			$name = "families_" . $a . "_template";
			// First split the html templates
			// This is done once and later used when exporting
			// data for the familty tree record.
			list ( $template [$a], $brackets [$a] ) = $this->splitTemplate ( 
					$parameter [$name] );
		}
		
		// get parameter for the export
		$col = $parameter ['color_family'];
		$node_style = $parameter ['node_style_family'];
		$col_border = $parameter ['border_family'];
		$box_width = $parameter ['box_width_family'];
		$border_width = $parameter ['border_width_family'];
		$font_size = $parameter ['font_size_family'];
		
		// loop over all families
		foreach ( $xrefFam as $xref ) {
			// increase time limit
			$this->increaseTimeLimit();
				
			$record = Family::getInstance ( $xref, $tree );
			
			// now replace the tags with record data
			// the algorithm is the same as for individuals (see above)
			foreach ( array ("label","description" 
			) as $a ) {
				
				$nodetext [$a] = $this->substitutePlaceHolder ( $record, $record,
						$template [$a], "graphml", $brackets [$a] );
				/*
				 * //$nodetext [$a] = "";
				 * $new_string = "";
				 * if ($template [$a]) {
				 * foreach ( $template [$a] as $comp ) {
				 * if ($comp ["type"] == "string") {
				 * $new_string .= $comp ["component"];
				 * } else {
				 * $tag_replacement = "";
				 * $format = $comp ["format"];
				 * switch ($comp ["component"]) {
				 * case "Marriage" :
				 * $marriage = $record->getMarriage ();
				 * if ($marriage) {
				 * $tag_replacement .= $format [0];
				 * }
				 * break;
				 * case "MarriageDate" :
				 * $tag_replacement .= $this->formatDate (
				 * $record->getMarriageDate (),
				 * $format [0] );
				 * break;
				 * case "MarriagePlace" :
				 * // $record->getMarriagePlace() does not work because
				 * // there is no exception handling in the function
				 * $marriage = $record->getMarriage ();
				 * if ($marriage) {
				 * $tag_replacement .= $this->formatPlace (
				 * $marriage->getPlace (),
				 * $format [0] );
				 * }
				 * break;
				 * case "Fact" :
				 * $tag_replacement .= $this->getFact ( $record,
				 * $comp ["fact"], $format [0] );
				 * break;
				 *
				 * case "Gedcom" :
				 * $tag_replacement = preg_replace ( "/\\n/",
				 * "<br>", $record->getGedcom () );
				 * break;
				 * }
				 * if ($tag_replacement != "") {
				 * // check for a {...} in $new_string and remove it
				 * $new_string = $this->removeBrackets (
				 * $new_string, $brackets [$a] );
				 * $nodetext [$a] .= $new_string . $this->substituteSpecialCharacters (
				 * $tag_replacement, "graphml" );
				 * $new_string = "";
				 * }
				 * }
				 * }
				 * }
				 *
				 * // now add a remaining string
				 * $new_string = $this->removeBrackets ( $new_string,
				 * $brackets [$a] );
				 * $nodetext [$a] .= $new_string;
				 *
				 * // remove remaining brackets
				 * $nodetext [$a] = preg_replace ( array ("/{/","/}/"
				 * ), array ("",""
				 * ), $nodetext [$a] );
				 */
				// $nodetext [$a] = preg_replace ( "/<html>\s*<\/html>/", "", $nodetext [$a] );
			}
			// for the "lable" < and > must be replaced
			// for description no replacement is required (not clear why this is the case)
			$nodetext ["label"] = str_replace ( "<", "&lt;", 
					$nodetext ["label"] );
			$nodetext ["label"] = str_replace ( ">", "&gt;", 
					$nodetext ["label"] );
			
			// count the number of rows to scale the box height accordingly
			$label_rows = count ( 
					explode ( "&lt;br&gt;", $nodetext ["label"] ) ) +
					 count ( explode ( "&lt;tr&gt;", $nodetext ["label"] ) );
			
			// write export data
			$buffer .= '<node id="' . $xref . '">' . "\n";
			
			$buffer .= '<data key="d1"><![CDATA[http://my.site.com/' . $xref .
					 '.html]]></data>' . "\n";
			$buffer .= '<data key="d2"><![CDATA[' . $nodetext ["description"] .
					 ']]></data>' . "\n";
			
			// if no label text then set visible flag to false
			// otherwise a box is created
			if ($nodetext ["label"] == "") {
				$visible = "false";
				$border = '<y:BorderStyle hasColor="true" type="line" color="' .
						 $col_border . '" width="' . $border_width . '"/>';
			} else {
				$visible = "true";
				$border = '<y:BorderStyle hasColor="false" type="line" width="' .
						 $border_width . '"/>';
			}
			
			// note fill color must be black
			// otherwise yed does not find the family nodes
			$buffer .= '<data key="d3"> <y:ShapeNode>' . '<y:Geometry height="' .
					 $box_width . '" width="' . $box_width . '" x="28" y="28"/>' .
					 '<y:Fill color="#000000" color2="#000000" transparent="false"/>';
			
			$buffer .= $border;
			$buffer .= '<y:NodeLabel alignment="center" autoSizePolicy="content" ' .
					 'backgroundColor="' . $col . '" hasLineColor="true" ' .
					 'lineColor="' . $col_border . '" ' .
					 'textColor="#000000" fontFamily="' . $parameter ['font'] .
					 '" fontSize="' . $font_size . '" ' .
					 'fontStyle="plain" visible="' . $visible .
					 '" modelName="internal" modelPosition="c" ' . 'width="' .
					 $box_width . '" height="' . (12 * $label_rows) .
					 '" x="10" y="10">';
			
			$buffer .= $nodetext ["label"];
			
			$buffer .= '</y:NodeLabel> <y:Shape type="' . $node_style . '"/>' .
					 '</y:ShapeNode> </data>' . "\n" . "</node>\n";
			
			// write data if buffer is full
			if (strlen ( $buffer ) > 65536) {
				$this->graphml_fwrite ( $gedout, $buffer );
				$buffer = '';
			}
		}
		
		/*
		 * Create edges from families to individuals
		 */
		
		$no_edge = 0;
		
		// loop over families
		foreach ( $xrefFam as $xref ) {
			$record = Family::getInstance ( $xref, $tree );
			
			// get all parents
			$parents = array ($record->getHusband (),$record->getWife () 
			);
			
			// loop over parents and add edges for parents
			foreach ( $parents as $parent ) {
				if ($parent) {
					$no_edge += 1;
					$buffer .= '<edge id="' . $no_edge . '" source="' .
							 $parent->getXref () . '" target="' . $xref . '">' .
							 "\n";
					
					$buffer .= '<data key="d6"> <y:PolyLineEdge> <y:Path sx="0.0" sy="17.5" tx="0.0" ty="-10"/> <y:LineStyle color="#000000" type="line" width="' .
							 $parameter ['edge_line_width'] .
							 '"/> <y:Arrows source="none" target="none"/> <y:BendStyle smoothed="false"/> </y:PolyLineEdge> </data>' .
							 "\n" . '</edge>' . "\n";
				}
			}
			
			// get all children and add edges for children
			$children = $record->getChildren ();
			
			foreach ( $children as $child ) {
				$no_edge += 1;
				$buffer .= '<edge id="' . $no_edge . '" source="' . $xref .
						 '" target="' . $child->getXref () . '">' . "\n";
				
				$buffer .= '<data key="d6"> <y:PolyLineEdge> <y:Path sx="0.0" sy="17.5" tx="0.0" ty="-10"/> <y:LineStyle color="#000000" type="line" width="' .
						 $parameter ['edge_line_width'] .
						 '"/> <y:Arrows source="none" target="none"/> <y:BendStyle smoothed="false"/> </y:PolyLineEdge> </data>' .
						 "\n" . '</edge>' . "\n";
			}
			
			// write data if buffer is full
			if (strlen ( $buffer ) > 65536) {
				$this->graphml_fwrite ( $gedout, $buffer );
				$buffer = '';
			}
		}
		
		// add footer and write buffer
		$buffer .= $this->graphmlFooter ();
		$this->graphml_fwrite ( $gedout, $buffer );
	}
	
	/**
	 * Export the data in graphml format
	 *
	 * This is the main module which export the familty tree in graphml
	 * format.
	 *
	 * @param Tree $tree
	 *        	Which tree to export
	 * @param resource $gedout
	 *        	Handle to a writable stream
	 */
	private function exportLatex(Tree $tree, $gedout) {
		
		// get parameter entered in the web form
		$parameter = $_GET;
		
		$name = "individuals_label_template";
		// First split the html templates
		// This is done once and later used when exporting
		// data for the familty tree record.
		list ( $template, $brackets ) = $this->splitTemplate ( 
					$parameter [$name] );
		
		// now generate an array with fact names to be replaced by symbols
		$fact_symbols = array();
		$fact_legend = array();
		foreach( explode("\n",$parameter["symbols"]) as $row) {
			$row = trim($row);
			$cols = explode(",",$row);
			if (count($cols)>1) {
				$fact_symbols[$cols[0]] = $cols[1];
			}
			if (count($cols)>2) {
				$fact_legend[$cols[0]] = $cols[1];
			}
		}
		
		// Get header.
		// Buffer the output. Lots of small fwrite() calls can be very
		// slow when writing large files (copied from one of the webtree modules).
		$buffer = $parameter ["preamble"];
		
		// no add legend
		if (count($fact_legend) > 0) {
			$buffer .= '\newcommand{\GedcomLegende}{\begin{compactenum}';
			foreach ($fact_legend as $key => $symbol) {
				$label = GedcomTag::getLabel($key);
				if (strlen($label) > 4 and substr($label,0,5) == '<span') {					
					$buffer .= '\item[' . $symbol . '] ' . $key;
				} else {
					$buffer .= '\item[' . $symbol . '] ' . GedcomTag::getLabel($key);						
				}
			}
			$buffer .= '\end{compactenum}}';
		}
		
		// now add bibliography
		$buffer .= $this->getReferences($tree);
		
		// now add title
		$buffer .= $parameter ["title"];
		
		// get record of individuals and families
		list ( $xrefInd, $xrefFam, $generationInd, $generationFam ) = $this->getRecords ( 
				$tree, TRUE );
		
		/*
		 * Create nodes for individuals
		 */
		
		// loop over all individuals
		$last_branch = 0;
		foreach ( $generationInd as $xref => $value ) {
			// increase time limit
			$this->increaseTimeLimit();
			
			$branch = $value ['branch'];
			$generation = $value ['generation'];
			$name = $value ['name'];
			$givenname = $value ['givenname'];
			
			if ($branch > $last_branch) {
				$buffer .= $parameter["hierarchy_tree"] . " " . $branch . "}" . "\n";
				$first_generation = $generation;
				$last_generation = $generation - 1;
				$last_branch = $branch;
			}
			
			if ($generation > $last_generation) {
				$buffer .= $parameter["hierarchy_generation"] . " " .
						 ($generation - $first_generation + 1) . "}" . "\n";
				$last_generation = $generation;
			}
			
			$record = Individual::getInstance ( $xref, $tree );
			
			// get parameter for the export
			$sex = $record->getSex ();
			if ($sex == "F") {
				$s = 'female';
			} elseif ($sex == "M") {
				$s = 'male';
			} else {
				$s = 'unknown';
			}
			
			// loop to create the output for the node label
			// and the node description
			
			$nodetext = $this->substitutePlaceHolder ( $record, $record,
						$template, "latex", $brackets, $fact_symbols );

			// no line break before $nodetext allowed
			$buffer .= $nodetext  . "\n";
			
			// write to file if buffer is full
			if (strlen ( $buffer ) > 65536) {
				$this->graphml_fwrite ( $gedout, $buffer );
				$buffer = '';
			}
		}
		
		// add footer and write buffer
		$buffer .= $parameter ["epilog"];
		$this->graphml_fwrite ( $gedout, $buffer );
	}
}

return new ExportGraphmlModule ( __DIR__ );