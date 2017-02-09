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
use Fisharebest\Webtrees\Functions\Functions;
use Symfony\Component\Finder\Tests\FakeAdapter\NamedAdapter;
use Symfony\Component\Finder\Iterator\FilenameFilterIterator;
use phpDocumentor\Reflection\DocBlock\Tag;

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
	public function getReportMenu() {
		return new Menu ( $this->getTitle (), 
				'module.php?mod=' . $this->getName () .
						 '&amp;mod_action=set_parameter', 
						'menu-report-' . $this->getName (), 
						array ('rel' => 'nofollow' 
						) );
	}
	
	/**
	 * Returns the title on tabs, menu
	 *
	 * @return string Title of the report
	 */
	public function getTitle() {
		return I18N::translate ( 'Export as graphml' );
	}
	
	/**
	 * Returns the title in the report sub-menu
	 *
	 * @return string Title of the report
	 */
	public function getReportTitle() {
		return I18N::translate ( 'Export as graphml' );
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
		return I18N::translate ( 'Export family tree in graphml format for yed.' );
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
	 * This module is the main entry function of this class.
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
			case 'set_parameter' :
				// open a form to define the export format
				$this->setParameter ();
				break;
				
			case 'export' :
				// file name is set to the tree name
				$download_filename = $WT_TREE->getName ();
				if (strtolower ( substr ( $download_filename, - 8, 8 ) ) !=
						 '.graphml') {
					$download_filename .= '.graphml';
				}
				
				// Stream the file straight to the browser.
				header ( 'Content-Type: text/plain; charset=UTF-8' );
				header ( 
						'Content-Disposition: attachment; filename="' .
								 $download_filename . '"' );
				$stream = fopen ( 'php://output', 'w' );
				// Set Byte Order Mark
				fwrite($stream, pack("CCC",0xef,0xbb,0xbf));
				$this->exportGraphml ( $WT_TREE, $stream );
				fclose ( $stream );
				
				// exit;
				break;
				
			case 'download_settings' :
				// download the formula data into a local file
				// file name is set to the tree name
				$download_filename = "export_graphml_settings.txt";
				
				// Stream the file straight to the browser.
				header ( 'Content-Type: text/plain; charset=UTF-8' );
				header ('Content-Disposition: attachment; filename="' .
								 $download_filename . '"' );
				$stream = fopen ( 'php://output', 'w' );
				// Set Byte Order Mark
				fwrite($stream, pack("CCC",0xef,0xbb,0xbf));
				
				// write parameter
				fwrite($stream, base64_encode( serialize($_GET)));
				
				fclose ( $stream );
				
				// exit;
				break;
				
			case 'upload_settings' :
				// upload the formula data into a local file
				// get temporary file name of the uploaded file on the server
				$f = $_FILES['uploadedfile']['tmp_name'];
				// now set the form fields
				$stream = fopen ( $f, 'r' );
				$d = fread($stream,filesize($f));
				$settings = unserialize( base64_decode($d));
					
				// update web side with uploaded values
				$this->setParameter($settings);

				// exit;
				break;
				
			default :
				http_response_code ( 404 );
		}
	}
	
	/**
	 * Generate a form to define the graphml format
	 * 
	 * This function generates a form to define the export parameter
	 * and to trigger the export by submit.
	 * @param array $settings The setting in the form    	       	
	 */
	private function setParameter($settings = NULL) {
		global $controller;
		
		// generate a standard page
		$controller = new PageController ();
		$controller->setPageTitle ( $this->getTitle () )->pageHeader ();
		
		$directory = WT_MODULES_DIR . $this->getName();
		
		// fillread settings if not passed
		if (is_null($settings)) {
			$filename = $directory . "/export_graphml_settings.txt";
			$myfile = fopen($filename, "r") or die("Unable to open file!");
			$settings = fread($myfile,filesize($filename));
			$settings = unserialize(base64_decode($settings));
		};
	
		// header line of the form
		echo '<div id="reportengine-page">
		<form name="setupreport" method="get" action="module.php">
		<input type="hidden" name="mod" value=', $this->getName (), '>';
		// <input type="hidden" name="mod_action" value="export">

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
		foreach (array("individuals", "families") as $s1) {
			echo '<tr><td class="descriptionbox width30 wrap" colspan="5">', I18N::translate (
					'Template for ' . $s1 ), '</td></tr>';
			
			foreach (array("label", "description") as $s2) {
				echo '<tr><td class="descriptionbox width30 wrap">', 
				I18N::translate ("Node " . $s2), '</td>';

				//$filename = $directory . "/template_" . $s1 . "_" . $s2 . ".xml";
				//$myfile = fopen($filename, "r") or die("Unable to open file!");
				//$s = fread($myfile,filesize($filename));
				$name = $s1 . "_" . $s2 . "_template";
				$s = $settings[$name];
				$nrow = substr_count($s, "\n") + 1;

				echo '<td class="optionbox" colspan="4">' .
						'<textarea rows="' . $nrow . '" cols="100" name="' . $name . '">';
				echo $s;
				//fclose($myfile);
				echo '</textarea></td></tr>';
				
			}
		}
		
		/*
		 * Keyword description
		 * 
		 * Creates a table which lists all keywords which can be used in templates.
		 * 
		 * 
		 */
		echo '<tr><td class="descriptionbox width30 wrap" rowspan="1">', I18N::translate ( 
				'Keywords' ), '</td>';
		echo '<td class="optionbox" colspan="4">' .
				 I18N::translate ('List of allowed keywords to be used in the templates.') . ' ' .	
				 I18N::translate ('The first two characters within a template define the identifier for a tag and the format part, e.g. @&.') .
				 '<table border="1">' .
				 '<tr><th>' . I18N::translate ('Tag') .'</th><th>' . I18N::translate ('Format') .
				 '</th><th>' . I18N::translate ('Example given identifier @&') .'</th></tr>' .
				 '<tr><td>GivenName</td><td>' . I18N::translate ('position list of given names') . ', "." ' .
				 I18N::translate('for abbreviation') . '</td><td>@GivenName&1,2,3.@</td></tr>' .
				 '<tr><td>SurName</td><td>-</td><td>@SurName@</td></tr>' .
				 '<tr><td>BirthDate, DeathDate, MarriageDate</td><td>' . I18N::translate('PHP date format specification') . '</td><td>@DeathDate&%j.%n.%Y@</td></tr>' .
				 '<tr><td>BirthPlace, DeathPlace, MarriagePlace</td><td>' . I18N::translate('list of positions, exclusion followed after') . ' /' . '</td><td>@DeathPlace&2,3/USA@</td></tr>' .
				 '<tr><td>Marriage<td>' . I18N::translate('any string') . '</td><td>@Marriage&oo@</td></tr>' .
				 '<tr><td>FactXXXX<td>' . I18N::translate('position in the ordered fact list') . '</td><td>@FactOCCU&1,2,-1@</td></tr>' .
				 '<tr><td>Portrait<td>"fallback"' .  I18N::translate('or') . '"silhouette"</td><td>@Portrai&fallback@</td></tr>' .
				 '<tr><td>Gedcom<td>-</td><td>@Gedcom@</td></tr>' .
				 '<tr><td>Remove<td>' .  I18N::translate('String to be removed') . '</td><td>@Remove&";","."@</td></tr>' .
				 '</table></td>';
		
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

		$choicelist = array("BevelNode2", "Rectangle", "RoundRect","BevelNode","BevelNodeWithShadow",
				"BevelNode3", "ShinyPlateNode", "ShinyPlateNodeWithShadow", "ShinyPlateNode2",
				"ShinyPlateNode3");
		echo '<tr>';
		foreach(array("male", "female", "unknown") as $s) {
			$name = "node_style_" . $s;
			$selected = $settings[$name];
			echo '<td class="optionbox"  colspan="1">' . 
					 '<select name="' . $name .'">';
			foreach($choicelist as $o) {
				echo '<option value="' . $o . '"';
			    if ($selected == $o) echo 'selected';
			    echo '>' . $o . '</option>';
			};
			/**
					 '<option value="BevelNode2" selected>BevelNode2</option>' .
					 '<option value="Rectangle">Rectangle</option>' .
					 '<option value="RoundRect">RoundRect</option>' .
					 '<option value="BevelNode">BevelNode</option>' .
					 '<option value="BevelNodeWithShadow">BevelNodeWithShadow</option>' .
					 '<option value="BevelNode3">BevelNode3</option>' .
					 '<option value="ShinyPlateNode">ShinyPlateNode</option>' .
					 '<option value="ShinyPlateNodeWithShadow">ShinyPlateNodeWithShadow</option>' .
					 '<option value="ShinyPlateNode2">ShinyPlateNode2</option>' .
					 '<option value="ShinyPlateNode3">ShinyPlateNode3</option>' .
					 '</select></td>';
					 **/
			echo '</select></td>';
		}
		
		$name = "node_style_family";
		$selected = $settings[$name];
		$choicelist = array("rectangle","roundrectangle","ellipse","parallelogram",
				"hexagon","triangle","rectangle3d","octagon3d","diamond","trapezoid",
				"trapezoid2");
		
		echo '<td class="optionbox"  colspan="1">' .
				 '<select name="node_style_family">';
		foreach($choicelist as $o) {
				echo '<option value="' . $o . '"';
			    if ($selected == $o) echo 'selected';
			    echo '>' . $o . '</option>';
		};
		/**
				 '<option value="rectangle">Rectangle</option>' .
				 '<option value="roundrectangle">Round Rectangle</option>' .
				 '<option value="ellipse">Ellipse</option>' .
				 '<option value="parallelogram">Parallelogram</option>' .
				 '<option value="hexagon">Hexagon</option>' .
				 '<option value="triangle">Triangle</option>' .
				 '<option value="rectangle3d">Rectangle 3D</option>' .
				 '<option value="octagon3d">Octagon</option>' .
				 '<option value="diamond" selected>Diamond</option>' .
				 '<option value="trapezoid">Trapezoid</option>' .
				 '<option value="trapezoid2">Trapezoid2</option>' .
		**/		 
		echo '</select></td></tr>';
		
		/*
		 * Box style - Fill color
		 * 
		 * Here the fill colors of the boxes are defined.
		 */ 
		/**
		echo '<tr><td class="optionbox" colspan="1">' .  I18N::translate ('Fill color') . 
				'<input type="color" value="#ccccff" name="color_male"></td>';
		echo '<td class="optionbox" colspan="1">' .  I18N::translate ('Fill color') . 
				'<input type="color" value="#ffcccc" name="color_female"></td>';
		echo '<td class="optionbox" colspan="1">' .  I18N::translate ('Fill color') . 
				'<input type="color" value="#ffffff" name="color_unknown"></td>';
		echo '<td class="optionbox" colspan="1">' .  I18N::translate ('Fill color') . 
				'<input type="color" value="#ffffff" name="color_family"></td></tr>';
				**/
		echo '<tr><td class="optionbox" colspan="1">' .  I18N::translate ('Fill color') .
				'<input type="color" value="' . $settings["color_male"] . '" name="color_male"></td>';
		echo '<td class="optionbox" colspan="1">' .  I18N::translate ('Fill color') .
				'<input type="color" value="' . $settings["color_female"] . '" name="color_female"></td>';
		echo '<td class="optionbox" colspan="1">' .  I18N::translate ('Fill color') .
				'<input type="color" value="' . $settings["color_unknown"] . '" name="color_unknown"></td>';
		echo '<td class="optionbox" colspan="1">' .  I18N::translate ('Fill color') .
				'<input type="color" value="' . $settings["color_family"] . '" name="color_family"></td></tr>';
		
		/*
		 * Box style - Border color
		 * 
		 * Here the border colors of the boxes are defined.
		 */ 
		echo '<tr>';
		foreach(array("male", "female", "unknown") as $s) {
			$name = "border_" . $s; 
			echo '<td class="optionbox" colspan="1">' .  I18N::translate ('Border color') .
					'<input type="color" value="' . $settings[$name] . '" name="' . $name . '"></td>';
		}
		foreach(array("family") as $s) {
			$name = "border_" . $s; 
			echo '<td class="optionbox" colspan="1">' .  I18N::translate ('Border color') .
					'<input type="color" value="' . $settings[$name] . '" name="' . $name . '"></td>';
		}		
		echo '</tr>';
		
		/*
		 * Box style - Box width
		 * 
		 * Here the widths of the boxes are defined.
		 */ 
		echo '<tr>';
		foreach(array("male", "female", "unknown") as $s) {
			$name = "box_width_" . $s; 
			echo '<td class="optionbox" colspan="1">' .  I18N::translate ('Box width') .
					'<input type="number" value="' . $settings[$name] . '" name="' . $name . '"></td>';
		}
		$name = "box_width_family"; 
		echo '<td class="optionbox" colspan="1">' . I18N::translate('Symbol') . " " .
		I18N::translate('width') . "/" . I18N::translate('height') .
				'<input type="number" value="' . $settings[$name] . '" name="' . $name . '"></td></tr>';
		
		/*
		 * Box style - Border line width
		 * 
		 * Here the widths of the border lines are defined.
		 */ 
		echo '<tr>';
		foreach(array("male", "female", "unknown", "family") as $s) {
			$name = "border_width_" . $s; 
			echo '<td class="optionbox" colspan="1">' . I18N::translate('Border width') .
					'<input type="number" value="' . $settings[$name] . '" step="0.1" name="' . $name . '"></td>';
		}
		echo '</tr>';
		
		/*
		 * Box style - Font size
		 * 
		 * Here the font sizes of the text are defined.
		 */ 
		echo '<tr>';
		foreach(array("male", "female", "unknown", "family") as $s) {
			$name = "font_size_" . $s; 
			echo '<td class="optionbox" colspan="1">' . I18N::translate('Font size') .
					'<input type="number" value="' . $settings[$name] . '" step="1" name="' . $name . '"></td>';
		}
		echo '</tr>';
		
		/*
		 * Box style - default silhouettes
		 * 
		 * Here the default silhouettes are defined.
		 */ 
		echo '<tr><td class="descriptionbox width30 wrap" rowspan="1">', I18N::translate ( 
				'Default portrait' ), '</td>';
		foreach(array("male", "female", "unknown") as $s) {
			$name = "default_portrait_" . $s; 
			echo '<td class="optionbox" colspan="1">
					<input type="text" size="30" value="' . $settings[$name] . '" name="' . $name . '"></td>';
		}
		
		echo '<td class="optionbox" colspan="1"/></tr>';
		
		/*
		 * Font type
		 * 
		 * Here the font type is defined.
		 */ 
		$name = "font";
		$selected = $settings[$name];
		$choicelist = array("Times New Roman","Dialog","Franklin Gothic Book","Bookman Old Style",
				"Lucida Handwriting",);
		
		echo '<tr><td class="descriptionbox width30 wrap">', I18N::translate ( 
				'Font' ), '</td><td class="optionbox" colspan="4">' .
				 '<select name="font">';
		foreach($choicelist as $o) {
			echo '<option value="' . $o . '"';
			if ($selected == $o) echo 'selected';
			echo '>' . $o . '</option>';
		};
				 /**	
				 '<option value="Times New Roman" selected>Times New Roman</option>' .
				 '<option value="Dialog">Dialog</option>' .
				 '<option value="Franklin Gothic Book">Franklin Gothic Book</option>' .
				 '<option value="Bookman Old Style">Bookman Old Style</option>' .
				 '<option value="Lucida Handwriting">Lucida Handwriting</option>' .
				 **/
		echo '</select></td></tr>';
						
		/*
		 * Edge line width
		 * 
		 * Here the width of edge lines is defined.
		 */ 
		echo '<tr><td class="descriptionbox width30 wrap">', I18N::translate ( 
				'Line width of edge' ), '</td><td class="optionbox" colspan="4">
			<input type="number" value="' . $settings["edge_line_width"] . '" step="0.1" name="edge_line_width" min="1" max="7"></td></tr>';
				
		// Submit button<
		echo '<tr><td class="topbottombar" colspan="6">', '<button name="mod_action" value="export">', I18N::translate ( 
				'Export Family Tree' ), '</button>', 
						'</td></tr>';
		//echo '</form>';
		
		// download settings
		//echo '<form name="download" method="get" action="module.php">
		//<input type="hidden" name="mod" value=', $this->getName (), '>';
		// <input type="hidden" name="mod_action" value="export">
		echo '<tr><td class="topbottombar" colspan="6">',
			'<button name="mod_action" value="download_settings">', I18N::translate (
				'Download Settings' ), '</button></td></tr>';
		echo '</table></form>';
		//echo '</table></form>';
		
		//echo '</div>';
		
		// upload settings
		//echo '<div id="reportengine-page">';
		echo '<table class="facts_table width50">';		
		echo '<tr><td class="descriptionbox width30 wrap" colspan="5">', I18N::translate (
					'Upload/Download Settings'), '</td></tr>';
		// header line of the form
		echo '<form name="upload" enctype="multipart/form-data" method="POST" 
				action="module.php?mod=' . $this->getName () . 
				'&mod_action=upload_settings">';
		//<input type="hidden" name="mod" value=', $this->getName (), '>';
		
		echo '<tr><td class="descriptionbox width30 wrap">', I18N::translate (
				'Read' ), '</td><td class="optionbox" colspan="4">',
			'<input name="uploadedfile" type="file"/>',
			'<button type="submit" value="upload_settings">', I18N::translate ( 
				'Upload Settings' ), '</button></td></tr>';
		echo '</table></form>';
		
		/**
		// download settings
		echo '<form name="download" method="get" action="module.php">
		<input type="hidden" name="mod" value=', $this->getName (), '>';
		// <input type="hidden" name="mod_action" value="export">
		echo '<tr><td class="descriptionbox width30 wrap">', I18N::translate (
				'Write' ),
			'<td class="optionbox" colspan="4">',
			'<button name="mod_action" value="download_settings">', I18N::translate (
				'Download Settings' ), '</button></td></tr>';
		echo '</table></form>';
		**/
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
	 * @param Individual $record record for an idividual    	       	
	 * @param string $format The format of the given name. It is a comma separated 
	 * list of numbers where each number stands for one of the given names. If a dot "."
	 * is given then all following given names are abbreviated.
	 * @return string The given name
	 */
	private function getGivenName($record, $format) {
		// first get the given name
		$tmp = $record->getAllNames ();
		$givn = $tmp [$record->getPrimaryName ()] ['givn'];

		// if $format is given then apply the format
		if ($givn && $format) {	
			$exp_givn = explode ( ' ', $givn );
			$count_givn = count ( $exp_givn );
			
			$exp_format = explode ( ",", $format );
			$givn = "";
			
			// loop over all parts of the given name and check if it is 
			// specified in the format 
			for ($i=0; $i < $count_givn; $i++) {
				$s = (string) $i+1;
				if (in_array($s,$exp_format)) {
					// given name to be included
					$givn .= " " . $exp_givn[$i];
				} elseif (in_array(".",$exp_format) || in_array($i . ".",$exp_format)) {
					// - if "." is included in the format list then all parts of the name 
					//   are included abbreviated
					// - a given name is also included abbreviated if the positions is 
					//   included in $format followed by "."
					$givn .= " " . $exp_givn[$i]{0} . "." ;
				}
			}
		}
	
		// now replace unknown names with three dots
		$givn = str_replace ( array ('@P.N.','@N.N.'), 
				array (I18N::translateContext ( 'Unknown given name', '…' ),
						I18N::translateContext ( 'Unknown surname', '…' ) 
				), trim($givn) );
		
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
	 * @param object $place A place object       	
	 * @param string $format The hierarchy levels to be returned.     	
	 * @return string
	 */
	private function formatPlace($place, $format) {
		$place_ret = "";
		// get the name of the place object
		if (is_object($place) && get_class($place) == "Fisharebest\Webtrees\Place") {	
			$place = $place->getGedcomName ();
		}
		if ($place) {
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
					$sarray =  explode ( "/", $s );
					$i = (int) $sarray[0];
					if (abs($i) <= $count_place && $i != 0) {
						// the required hierarch level must exists
						if ($i > 0) {
							// hierarchy level counted from left
							$sp = trim($exp_place [$i - 1]);
						} else {
							// hierarchy level counted from right
							$sp = trim($exp_place [$count_place + $i]);
						}
						// check if name should be omitted
						if (in_array($sp, $sarray)) $sp ="";
						
						// add comma separator
						if ($place_ret != "" & $sp != "") $place_ret .= ", ";
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
	 * @param string $format A standard PHP date format.        	
	 * @return string
	 */
	private function formatDate($date, $format) {
		if ($date instanceof Date) {
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
	 * @param string $buffer The string to be written in the file        	
	 */
	private function graphml_fwrite($gedout, $buffer) {
				
		fwrite ( $gedout, mb_convert_encoding($buffer,'UTF-8'));
	}
	
	/**
	 * Returns the portrait file name
	 *
	 * This module returns the file name of the portrait of an individual.
	 *
	 * @param Individual $record The record of an idividual
	 * @param string $format If $format = "silhouette" then allways the fallback 
	 * picture is used. If $format = "fallback" then the portrait is used and only
	 * if this is not defined the fallback picture is used.
	 * @param string $servername If $servername = true then the server file name
	 * including the path is returned.
	 * @return string The file name of the portrait
	 */
	private function getPortrait($record, $format, $servername = false) {
		$portrait_file = "";
		
		// get the fallback picture
		// the name is defined in the report form
		if ($format == "silhouette" || $format == "fallback") {
			$sex = $record->getSex ();
			if ($sex == "F") {$s = 'female';
			} elseif ($sex == "M") {$s = 'male';
			} else {$s = 'unknown';
			}
			if (array_key_exists('default_portrait_' . $s,$_GET )) {
				$portrait_fallback = $_GET ['default_portrait_' . $s];
			} else {
				$portrait_fallback = "";
			}
		}


		if ($format == "silhouette") {
			// return the fallback figure if $format == "silhouette"
			$portrait_file  = $portrait_fallback;
		} else {
			$portrait = $record->findHighlightedMedia ();
			if ($portrait) {
				if ($servername) {
					// get the full server name including path
					$portrait_file  = $portrait->getServerFilename();
				} else {
					// get the file name without full server path
					$portrait_file  = $portrait->getFilename();
				}
			}
			If ($format == "fallback" && $portrait_file == "") $portrait_file = $portrait_fallback;
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
		$format_Size = $format[0];
		if (count($format) > 1) {
			$format_default = $format[1];
		} else {
			$format_default = "";
		}
		
		$portrait_file = $this->getPortrait($record, "", true);
		$image_length = $format_default;
		
		if ($portrait_file != "" && strlen($format_Size) > 1) {
			$constraint = $format_Size{0};
			$size_constraint = (float) substr($format_Size,1);
			$image_size = getimagesize($portrait_file);
			$width = $image_size[0];
			$height = $image_size[1];

			if ($constraint == "w") {
				// constraint is the width, get the height
				if ($width != 0) $image_length = (int) ($height * $size_constraint / $width);
			} else {
				// constraint is the height, get the width
				if ($height != 0) $image_length = (int) ($width * $size_constraint / $height);
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
	 * @param Individual $record The record for which the facts are returned
	 * @param string $fact The gedcom identifier of the fact, e.g. "OCCU"
	 * @param string $format A list of positions in the ordered fact list which are returned
	 * @return string A comma separted list of facts
	 */
	private function getFact($record, $fact, $format) {
		// get all facts with identifier $fact as ordered array
		$fact_string = "";
		$Facts = $record->getFacts ( $fact , true);
		if ($Facts) {
			if (! $format) {
				// if $format is not given return all items
				foreach ($Facts as $Fact) {
					if ($fact_string != "") $fact_string .= $fact_string;
					$fact_string .= $Fact->getValue ();
				}
			} else {
				// selects the items from the fact array as defined
				// in the $format parameter
				$exp_format = explode ( ",", $format );
				$count_facts = count ($Facts);
				// fact list is used to avoid having facts twice
				$fact_list = array();
				// loop over all components of $format
				foreach ( $exp_format as $s ) {
					$i = (int) $s;
					// check if item position exists
					if (abs($i) <= $count_facts && $i != 0) {						
						if ($i > 0) {
							$j = $i -1;
						} else {
							// if position is negativ count from the end
							$j = $count_facts + $i;
						}
						$fact_value = trim($Facts [$j]->getValue ());
						if (!in_array($fact_value, $fact_list)) {
							// add a separator
							if ($fact_string != "") $fact_string .= ", ";
							$fact_list[] = $fact_value;
							$fact_string .= $fact_value;
						}
					}
				}
			}
		}
				
		return trim($fact_string);
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
				 "\n" . '<key for="edge" id="d6" yfiles.type="edgegraphics"/>' . "\n" .
		"\n" . '<graph edgedefault="directed" id="G">' . "\n";
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
	 * @param String $template The template to be decomposed.   	
	 * @return Array Each element of the array is itself an array.
	 * Each of these arrays consist of 4 elements. 
	 * Element "type" defines if the template component is a string ("string")
	 * or a tag ("tag"). The "component" contains either the string or the tag
	 * name. "format" contains a format array and "fact" the gedcom fact identifier
	 * in case the tag is "Fact".
	 */
	private function splitTemplate($template) {
		// check that the template has at least two characters
		if (strlen ( $template ) > 2) {
			// extract the symbols identifying tags and format descriptions
			$tag = $template {0};
			$format = $template {1};
				
			// remove line breaks
			$template = trim ( preg_replace ( '/\s+/', ' ', $template ) );

			// start with an "{" to remove everything if no data are found
			$template_array = array (
					array ("component" => '{', "type" => 'string',
							"format" => "" , "fact" => ""
					) 
			);
			
			$i = 1;
			$pos_end = 1;
			$pos = 0;
			
			// now split the template searching for the next tag symbol
			// $pos is the position of the next tag symbol
			// $pos_end is the position of the 
			while ( $pos !== false ) {
				$pos = strpos ( $template, $tag, $pos_end + 1 );
				if ($pos === false) {
					// no additional tag symbol found
					if ($pos_end + 1 < strlen ( $template )) {
						// there is a terminating string at the end of the template
						// add the string to the return array
						// substring {...} are removed
						$template_array [$i] = array (
								"component" => 	$this->removeBrackets(substr ( $template, 
										$pos_end + 1 )),"type" => "string",
								"format" => "" , "fact" => ""
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
								"component" => $this->removeBrackets(substr ( $template, 
										$pos_end + 1, $pos - $pos_end - 1 )),
								"type" => "string","format" => "" , "fact" => ""
						);
						$i ++;
					}
					
					// now the tag is added to the return array
					// search for the end of the tag
					$pos_end = strpos ( $template, $tag, $pos + 1 );
					
					if ($pos_end !== false) {
						// get the format definition
						$pos_format = strpos ( $template, $format, $pos );
						
						if ($pos_format < $pos_end && $pos_format !== false) {
							// a format definition exists, split it
							$format_array = explode($format, substr ( $template, 
											$pos_format + 1, 
											$pos_end - $pos_format - 1 ));
							// add the tag to the return array
							$template_array [$i] = array (
									"component" => substr ( $template, 
											$pos + 1, $pos_format - $pos - 1 ),
									"type" => "tag",
									"format" => $format_array , "fact" => ""
							);
							$i ++;
						} else {
							// there is not format definition
							// add the tag to the return array
							$template_array [$i] = array (
									"component" => substr ( $template, 
											$pos + 1, $pos_end - $pos - 1 ),
									"type" => "tag","format" => array("") , "fact" => ""
							);
							$i ++;
						}
					}
				}
			}
			
			// end with an "}" matching the "{" at the beginning
			$template_array [$i] = array ("component" => '}',
					"type" => 'string',"format" => '', "fact" => ''
			);
			
			// now serach for tags defining facts and filling the 
			// "fact" array element
			for ($j=0; $j < $i; $j++) {
				if (substr($template_array [$j]["component"], 0, 4) == "Fact") {
					$template_array [$j]["fact"] = substr($template_array [$j]["component"], 4);
					$template_array [$j]["component"] = "Fact";
				}
			}
		} else {
			$template_array = null;
		}
		
		return $template_array;
	}
	
	/**
	 * Remove brackets
	 * 
	 * This module removes substring {...} within a string.
	 *
	 * @param string $subject the input string where brackets should be 
	 * removed.
	 * @return string The input string where brackets are removed.
	 */
	private function removeBrackets($subject) {
		$count = 1;
		// take into account that there might be multiple brackets.
		while($count > 0) {
			// use regular expressions to remove brackets
			$subject = preg_replace ( "/{[^{}]*}/", "",
					$subject, -1, $count );
		}
		return $subject;
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
	 * @param string $subject the input string where special characters should 
	 * be substituted.
	 * @return string The input string with substituted characters
	 */
	private function substituteSpecialCharacters($subject) {

		//$subject = preg_replace ( '/&nbsp;/', ' ', $subject );
		
		$subject = preg_replace ( "/&/", "&amp;", $subject);
		//$subject = preg_replace ( "/&(?![^ ][^&]*;)/", "&amp;", $subject);
		$subject = preg_replace ( "/\"/", "&quot;", $subject);
		$subject = preg_replace ( "/\'/", "&apos;", $subject);
		$subject = preg_replace ( "/(?<!br)>/", "&gt;", $subject);
		$subject = preg_replace ( "/<(?!br>)/", "&lt;", $subject);

		return $subject;
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
		
		// First split the html templates
		// This is done once and later used when exporting
		// data for the familty tree record.
		$template ["label"] = $this->splitTemplate ( 
				$parameter ["individuals_label_template"] );
		$template ["description"] = $this->splitTemplate ( 
				$parameter ["individuals_description_template"] );
		
		// Get header.
		// Buffer the output. Lots of small fwrite() calls can be very 
		// slow when writing large files (copied from one of the webtree modules).
		$buffer = $this->graphmlHeader ();
		
		/*
		 * Create nodes for individuals
		 */
		// Get all individuals
		$rows = Database::prepare ( 
				"SELECT i_id AS xref, i_gedcom AS gedcom" .
						 " FROM `##individuals` WHERE i_file = :tree_id ORDER BY i_id" )->execute ( 
				array ('tree_id' => $tree->getTreeId () 
				) )->fetchAll ();
		
		// loop over all individuals
		foreach ( $rows as $row ) {
			$record = Individual::getInstance ( $row->xref, $tree );
			
			// get parameter for the export
			$sex = $record->getSex ();
			if ($sex == "F") {$s = 'female';
			} elseif ($sex == "M") {$s = 'male';
			} else {$s = 'unknown';
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
				
				/* replace tags in the template with data 
				 
				 Algorithm:
				 - loop over all template components
				 - unless no tag with data is found concatenate all strings
				   and store it in $new_string
				 - if a tag for which gedcom data exist is found then
				   remove all brackets {...} in $new_string and add $new_string
				   and the "tag data" to the string $nodetext[$a]. Then set 
				   $new_string ="".
				*/
				
				$nodetext [$a] = "";
				$new_string = "";
				if ($template [$a]) {
					// loop over all template components
					foreach ( $template [$a] as $comp ) {
						if ($comp ["type"] == "string") {
							// element is a string, add it to $new_string
							$new_string .= $this->substituteSpecialCharacters($comp ["component"]);
						} else {
							// element is a tag, get the tag data
							$tag_replacement = "";
							$format = $comp ["format"];
							switch ($comp ["component"]) {
								case "GivenName" :
									$tag_replacement .= $this->getGivenName ( 
											$record, $format[0] );
									break;
								case "SurName" :
									$tag_replacement .= $record->getAllNames () [$record->getPrimaryName ()] ['surname'];
									$tag_replacement = str_replace ( '@N.N.',
											I18N::translateContext ( 'Unknown surname', '…' ), 
										 	$tag_replacement );
									break;
								case "BirthDate" :
									$tag_replacement .= $this->formatDate ( 
											$record->getBirthDate (), $format[0] );
									break;
								case "BirthPlace" :
									$tag_replacement .= $this->formatPlace ( 
											$record->getBirthPlace (), $format[0] );
									break;
								case "DeathDate" :
									$tag_replacement .= $this->formatDate ( 
											$record->getDeathDate (), $format[0] );
									break;
								case "DeathPlace" :
									$tag_replacement .= $this->formatPlace ( 
											$record->getDeathPlace (), $format[0] );
									break;
								case "MarriageDate" :
									$tag_replacement .= $this->formatDate ( 
											$record->getMarriageDate (), $format[0] );
									break;
								case "MarriagePlace" :
									$tag_replacement .= $this->formatPlace ( 
											$record->getMarriagePlace (), 
											$format[0] );
									break;
								case "Fact" :
									$tag_replacement .= $this->getFact ( 
											$record, $comp ["fact"], $format[0] );
									break;
								case "Portrait" :
									$tag_replacement .= $this->getPortrait($record,$format[0]);
									break;
								case "PortraitSize" :
									$tag_replacement = $this->getPortraitSize($record, $format);
									break;
								case "Gedcom" :
									$tag_replacement = preg_replace ( "/\\n/", "<br>", $record->getGedcom() );
									break;
								case "Remove" :
									if ($new_string != "") {
										$new_string = $this->removeBrackets($new_string );
										$new_string = preg_replace ( "/\Q" . $format[0] . "\E(?=[\}\s]*$)/", "", $new_string);
									} else {
										$nodetext[$a] = preg_replace ( "/\Q" . $format[0] . "\E(?=[\}\s]*$)/", "", $nodetext[$a]);
									}
									break;
							}
							//if ($tag_replacement != "" or $comp ["component"] == "Remove") {
							if ($tag_replacement != "") {
								// data for the tag exists
								// check for a {...} in $new_string and remove it
								$new_string = $this->removeBrackets($new_string );
								// add $new_string to $nodetext[$a]
								$nodetext [$a] .= $new_string . $this->substituteSpecialCharacters($tag_replacement);
								$new_string = "";
							}
							/**if ($comp ["component"] == "Remove") {
								// remove string 
								$nodetext[$a] = preg_replace ( "/\Q" . $format[0] . "\E(?=[\}\s]*$)/", "", $nodetext[$a]);
							}**/
						}
					}
				}
				
				// add remaining strings to $nodetext
				$new_string = $this->removeBrackets($new_string );
				$nodetext [$a] .= $new_string;
				// remove all remaining brackets (which contain record data)
				$nodetext [$a] = preg_replace ( array ("/{/","/}/" 
				), array ("","" 
				), $nodetext [$a] );
				$nodetext [$a] = preg_replace ( "/<html>\s*<\/html>/", "", $nodetext [$a] );
			}
			
			// the replacement of < and > has to be done for "lable"
			// for "description" no replacement must be done (not clear why)
			$nodetext ["label"] = str_replace ( "<", "&lt;", 
					$nodetext ["label"] );
			$nodetext ["label"] = str_replace ( ">", "&gt;", 
					$nodetext ["label"] );

			// count the number of rows to set the box height accordingly
			$label_rows = count ( explode ( "&lt;br&gt;", $nodetext ["label"] ) ) +
							count ( explode ( "&lt;tr&gt;", $nodetext ["label"] ) ) + 1;

			// create export for the node 
			$buffer .= '<node id="' . $row->xref . '">' . "\n" .
					 '<data key="d1"><![CDATA[http://my.site.com/' . $row->xref .
					 '.html]]></data>' . "\n" . '<data key="d2"><![CDATA[' .
					 $nodetext ["description"] . ']]></data>' . "\n" .
					 '<data key="d3">' . '<y:GenericNode configuration="' .
					 $node_style . '"> <y:Geometry height="' .
					 (12 * $label_rows) .
					 '" width="' . $box_width . '" x="10" y="10"/> <y:Fill color="' . $col .
					 '" transparent="false"/> <y:BorderStyle color="' .
					 $col_border .
					 '" type="line" width="' . $border_width . '"/> <y:NodeLabel alignment="center" autoSizePolicy="content" hasBackgroundColor="false" hasLineColor="false" textColor="#000000" fontFamily="' . $parameter ['font'] . '" fontSize="' . $font_size . '" fontStyle="plain" visible="true" modelName="internal" modelPosition="l" width="129" height="19" x="1" y="1">';
			
			// no line break before $nodetext allowed
			$buffer .= $nodetext ["label"] . "\n" .
					 '</y:NodeLabel> </y:GenericNode> </data>' . "\n" .
					 "</node>\n";
			
			// write to file if buffer is full
			if (strlen ( $buffer ) > 65536) {
				$this->graphml_fwrite($gedout, $buffer);
				$buffer = '';
			}
		}
		
		/*
		 * Create nodes for families
		 */
		// First split the html templates
		// This is done once and later used when exporting
		// data for the familty tree record.
		$template ["label"] = $this->splitTemplate ( 
				$parameter ["families_label_template"] );
		$template ["description"] = $this->splitTemplate ( 
				$parameter ["families_description_template"] );

		// get parameter for the export
		$col = $parameter ['color_family'];
		$node_style = $parameter ['node_style_family'];
		$col_border = $parameter ['border_family'];
		$box_width = $parameter ['box_width_family'];
		$border_width = $parameter ['border_width_family'];
		$font_size = $parameter ['font_size_family'];
		
		// Get all family records
		$rows = Database::prepare ( 
				"SELECT f_id AS xref, f_gedcom AS gedcom" .
						 " FROM `##families` WHERE f_file = :tree_id ORDER BY f_id" )->execute ( 
				array ('tree_id' => $tree->getTreeId () 
				) )->fetchAll ();
		
		// loop over all families
		foreach ( $rows as $row ) {
			$record = Family::getInstance ( $row->xref, $tree );
			
			// now replace the tags with record data
			// the algorithm is the same as for individuals (see above)
			foreach ( array ("label","description" 
			) as $a ) {
				
				$nodetext [$a] = "";
				$new_string = "";
				if ($template [$a]) {
					foreach ( $template [$a] as $comp ) {
						if ($comp ["type"] == "string") {
							$new_string .= $comp ["component"];
						} else {
							$tag_replacement = "";
							$format = $comp ["format"];
							switch ($comp ["component"]) {
								case "Marriage" :
									$marriage = $record->getMarriage ();
									if ($marriage) {
										$tag_replacement .= $format[0];
									}
									break;
								case "MarriageDate" :
									$tag_replacement .= $this->formatDate ( 
											$record->getMarriageDate (), $format[0] );
									break;
								case "MarriagePlace" :
									// $record->getMarriagePlace() does not work because 
									// there is no exception handling in the function
									$marriage = $record->getMarriage ();
									if ($marriage) {
										$tag_replacement .= $this->formatPlace ( 
												$marriage->getPlace (), $format[0] );
									}
									break;
								case "Gedcom" :
									$tag_replacement = preg_replace ( "/\\n/", "<br>", $record->getGedcom() );
									break;
							}
							if ($tag_replacement != "") {
								// check for a {...} in $new_string and remove it
								$new_string = $this->removeBrackets($new_string );
								$nodetext [$a] .= $new_string . $this->substituteSpecialCharacters($tag_replacement);
								$new_string = "";
							}
						}
					}
				}
				
				// now add a remaining string
				$new_string = $this->removeBrackets($new_string );
				$nodetext [$a] .= $new_string;
				
				// remove remaining brackets
				$nodetext [$a] = preg_replace ( array ("/{/","/}/" 
				), array ("","" 
				), $nodetext [$a] );
				//$nodetext [$a] = preg_replace ( "/<html>\s*<\/html>/", "", $nodetext [$a] );
				
			}
			// for the "lable" < and > must be replaced
			// for description no replacement is required (not clear why this is the case)
			$nodetext ["label"] = str_replace ( "<", "&lt;", 
					$nodetext ["label"] );
			$nodetext ["label"] = str_replace ( ">", "&gt;", 
					$nodetext ["label"] );
			
			// count the number of rows to scale the box height accordingly
			$label_rows = count ( explode ( "&lt;br&gt;", $nodetext ["label"] ) ) +
							count ( explode ( "&lt;tr&gt;", $nodetext ["label"] ) );
				
							
			// write export data
			$buffer .= '<node id="' . $row->xref . '">' . "\n";
			
			$buffer .= '<data key="d1"><![CDATA[http://my.site.com/' . $row->xref .
					 '.html]]></data>' . "\n";
			$buffer .= '<data key="d2"><![CDATA[' . $nodetext ["description"] .
					 ']]></data>' . "\n";
			
			
			// if no label text then set visible flag to false
			// otherwise a box is created
			if ($nodetext ["label"] == "") {
				$visible = "false";
				$border = '<y:BorderStyle hasColor="true" type="line" color="' . $col_border . '" width="' . $border_width . '"/>';
			} else {
				$visible = "true";
				$border = '<y:BorderStyle hasColor="false" type="line" width="' . $border_width . '"/>';
			}
					
			// note fill color must be black
			// otherwise yed does not find the family nodes
			$buffer .= '<data key="d3"> <y:ShapeNode>' .
					 '<y:Geometry height="'. 
					 $box_width . '" width="' .
					 $box_width . '" x="28" y="28"/>' . 
					 '<y:Fill color="#000000" color2="#000000" transparent="false"/>';
			
			$buffer .=		 $border;
			$buffer .=	'<y:NodeLabel alignment="center" autoSizePolicy="content" ' .
					 'backgroundColor="' . $col . '" hasLineColor="true" ' . 'lineColor="' . $col_border . '" ' .
					 'textColor="#000000" fontFamily="' . $parameter ['font'] . '" fontSize="' . $font_size . '" ' .
					 'fontStyle="plain" visible="' . $visible . '" modelName="internal" modelPosition="c" ' .
					 'width="' . $box_width . '" height="' . (12 * $label_rows) . '" x="10" y="10">';
					
					 
			$buffer .= $nodetext ["label"];
			
			$buffer .= '</y:NodeLabel> <y:Shape type="' .
					 $node_style . '"/>' .
					 '</y:ShapeNode> </data>' . "\n" . "</node>\n";

			// write data if buffer is full
			if (strlen ( $buffer ) > 65536) {
				$this->graphml_fwrite($gedout, $buffer);
				$buffer = '';
			}
		}
		
		/*
		 * Create edges from families to individuals
		 */
		
		$no_edge = 0;
		
		// loop over families
		foreach ( $rows as $row ) {
			$record = Family::getInstance ( $row->xref, $tree );
			
			// get all parents
			$parents = array ($record->getHusband (),$record->getWife () 
			);
			
			// loop over parents and add edges for parents
			foreach ( $parents as $parent ) {
				if ($parent) {
					$no_edge += 1;
					$buffer .= '<edge id="' . $no_edge . '" source="' .
							 $parent->getXref () . '" target="' . $row->xref .
							 '">'  . "\n";
					
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
				$buffer .= '<edge id="' . $no_edge . '" source="' . $row->xref .
						 '" target="' . $child->getXref () . '">' . "\n";
				
				$buffer .= '<data key="d6"> <y:PolyLineEdge> <y:Path sx="0.0" sy="17.5" tx="0.0" ty="-10"/> <y:LineStyle color="#000000" type="line" width="' .
						 $parameter ['edge_line_width'] .
						 '"/> <y:Arrows source="none" target="none"/> <y:BendStyle smoothed="false"/> </y:PolyLineEdge> </data>' .
						 "\n" . '</edge>' . "\n";
			}
			
			// write data if buffer is full
			if (strlen ( $buffer ) > 65536) {
				$this->graphml_fwrite($gedout, $buffer);
				$buffer = '';
			}
		}
		
		// add footer and write buffer
		$buffer .= $this->graphmlFooter ();
		$this->graphml_fwrite( $gedout, $buffer);
	}
}

return new ExportGraphmlModule ( __DIR__ );