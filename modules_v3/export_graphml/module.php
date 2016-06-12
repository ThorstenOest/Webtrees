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
// namespace Thorsten\WebtreesAddOn\export_graphml;
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
use Fisharebest\Webtrees\Controller\IndividualController;

/**
 * Class ExportGraphmlModule
 *
 * This class provides a report which exports a tree in a file in graphml format.
 * The graphml format can be imported by yed to generate family tree charts.
 * yed support all-in-one charts.
 */
class ExportGraphmlModule extends AbstractModule implements 
		ModuleReportInterface {
	/**
	 * Return a menu item for this report.
	 * When selected it called the function modAction with parameter
	 * mod_action=set_parameter.
	 *
	 * @return Menu
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
	 * @return string
	 */
	public function getTitle() {
		return I18N::translate ( 'Export Graphml' );
	}
	
	/**
	 * Returns the title in the report sub-menu
	 *
	 * @return string
	 */
	public function getReportTitle() {
		return I18N::translate ( 'Export Graphml' );
	}
	
	/**
	 * A sentence describing what this module does.
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
	 *
	 * @param string $mod_action
	 *        	= 'set_parameter'
	 *        	opens a form to get parameter for the export
	 * @param string $mod_action
	 *        	= 'export' writes the grapgml file
	 */
	public function modAction($mod_action) {
		global $WT_TREE;
		
		switch ($mod_action) {
			case 'set_parameter' :
				// open a form to get the parameter for the export
				$this->setParameter ();
				break;
			case 'export' :
				
				// file name is the tree name
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
				$this->exportGraphml ( $WT_TREE, $stream );
				fclose ( $stream );
				
				// exit;
				break;
			default :
				http_response_code ( 404 );
		}
	}
	
	/**
	 * This function generates a form to get the export parameter
	 * and to trigger the export by submit.
	 */
	private function setParameter() {
		global $controller;
		
		// generate a standard page
		$controller = new PageController ();
		$controller->setPageTitle ( $this->getTitle () )->pageHeader ();
		
		// header
		echo '<div id="reportengine-page">
		<form name="setupreport" method="get" action="module.php">
		<input type="hidden" name="mod" value=', $this->getName (), '>
		<input type="hidden" name="mod_action" value="export">

		<table class="facts_table width50">
		<tr><td class="topbottombar" colspan="7">', I18N::translate ( 
				'Export tree in graphml format' ), '</td></tr>';
		
		// Node / description header
		echo '<td class="descriptionbox width30 wrap">', '</td>';
		echo '<td class="descriptionbox width30 wrap" colspan="2">', I18N::translate ( 
				'Node label' ), '</td>';
		echo '<td class="descriptionbox width30 wrap" colspan="2">', I18N::translate ( 
				'Node description' ), '</td>';
		
		// Node templates
		echo '<tr><td class="descriptionbox width30 wrap" rowspan="1">', I18N::translate ( 
				'Template for individuals' ), '</td>';
		echo '<td class="optionbox" colspan="2">' .
				 '<textarea rows="5" cols="40" name="ind_label_template">' .
				 '@&' . 
				 '<table width="200">' .
				 '{<td><img src="file:C:/xampp/htdocs/webtrees/data/media/thumbs/@Portrait&fallback@' .
						 '" alt="kein Bild" width="20" height="30"></td>}' . "\n" .
				 '<td><table><br><big>{@GivenName&1,2,3.@ }@SurName@</big><small> {*@BirthDate&%Y@}{ +@DeathDate&%Y@}</small></br>' . "\n" .
				 '{<br><small>@FactOCCU&-1@</small></br>}</table></td></table>' . '</textarea></td>';
		echo '<td class="optionbox" colspan="2">' .
				 '<textarea rows="5" cols="40" name="ind_description_template">' .
				 '@&' .
				 '<table><br>{@GivenName&1,2,3,.@ }@SurName@</br>' . "\n" . 
				 '{<br>{*@BirthDate&%j.%n.%Y@}{ @BirthPlace&1,-1@}</br>}' . "\n" .
				 '{<br>+@DeathDate&%j.%n.%Y@}{ @DeathPlace&1,-1@}</br>}' . "\n" .
				 '{<br>@FactOCCU@</br>}</table>' . '</textarea></td></tr>';
		
		// Family templates
		echo '<tr><td class="descriptionbox width30 wrap" rowspan="1">', I18N::translate ( 
				'Template for families' ), '</td>';
		echo '<td class="optionbox" colspan="2">' .
				 '<textarea rows="5" cols="40" name="family_label_template">' .
				 "@&@Marriage&oo@{ <small>@MarriageDate&%Y@</small>}" .
				 '</textarea></td>';
		echo '<td class="optionbox" colspan="2">' .
				 '<textarea rows="5" cols="40" name="family_description_template">' .
				 "@&<table>{<br>@Marriage&oo@ @MarriageDate&%j.%n.%Y@</br>}\n" .
				 "{<br>@MarriagePlace&-2,1@)</br>}</table>" .
				 '</textarea></td></tr>';
		
		// keyword description
		echo '<tr><td class="descriptionbox width30 wrap" rowspan="1">', I18N::translate ( 
				'Keyword' ), '</td>';
		echo '<td class="optionbox" colspan="4">' .
				 'The first two characters define the identifier for a tag and the format, eg. @$.' .
				 '<table border="1">' .
				 '<tr><th>tag</th><th>format</th><th>example for identifier @$</th></tr>' .
				 '<tr><td>GivenName</td><td>list of given names, "." for abreviation</td><td>@GivenName$1,2,3.@</td></tr>' .
				 '<tr><td>SurName</td><td>-</td><td>@SurName@</td></tr>' .
				 '<tr><td>BirthDate, DeathDate, MarriageDate</td><td> PHP date format specification</td><td>@DeathDate$%j.%n.%Y@</td></tr>' .
				 '<tr><td>BirthPlace, DeathPlace, MarriagePlace</td><td>"r" or "l" followed by the positions</td><td>@DeathPlace$r2,3@</td></tr>' .
				 '<tr><td>Marriage<td>any string</td><td>@Marriage$oo@</td></tr>' .
				 '<tr><td>FactXXXX<td>position in the ordered fact list</td><td>@FactOCCU$1,2,-1@</td></tr>' .
				 '</table></td>';
		
		// Box style header
		echo '<tr><td class="descriptionbox width30 wrap" rowspan="5">', I18N::translate ( 
				'Box style' ), '</td>';
		echo '<td class="descriptionbox width30 wrap"  colspan="1">', I18N::translate ( 
				'Male' ), '</td>';
		echo '<td class="descriptionbox width30 wrap"  colspan="1">', I18N::translate ( 
				'Female' ), '</td>';
		echo '<td class="descriptionbox width30 wrap"  colspan="1">', I18N::translate ( 
				'Unknown sex' ), '</td>';
		echo '<td class="descriptionbox width30 wrap"  colspan="1">', I18N::translate ( 
				'Family' ), '</td></tr>';
		
		// node type
		echo '<tr>';
		foreach(array("male", "female", "unknown") as $s) {
			echo '<td class="optionbox"  colspan="1">' . 
					 '<select name="node_style_' . $s .'" value="BevelNode2">' .
					 '<option value="BevelNode2">BevelNode2</option>' .
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
		}
		
		echo '<td class="optionbox"  colspan="1">' .
				 '<select name="node_style_family" value="BevelNode2">' .
				 '<option value="rectangle">rectangle</option>' .
				 '</select></td></tr>';
		
		// Fill color
			echo '<tr><td class="optionbox" colspan="1">Fill color 
					<input type="color" value="#d9ffff" name="color_male"></td>';
			echo '<td class="optionbox" colspan="1">Fill color 
					<input type="color" value="#ffd7ff" name="color_female"></td>';
			echo '<td class="optionbox" colspan="1">Fill color 
					<input type="color" value="#ffffff" name="color_unknown"></td>';
			echo '<td class="optionbox" colspan="1">Fill color
					<input type="color" value="#ffffff" name="color_family"></td></tr>';
		
		// Border color
		echo '<tr>';
		foreach(array("male", "female", "unknown", "family") as $s) {
			echo '<td class="optionbox" colspan="1">Border color
					<input type="color" value="#c0c0c0" name="border_' . $s . '"></td>';
		}
		echo '</tr>';
		
		// Box width
		echo '<tr>';
		foreach(array("male", "female", "unknown") as $s) {
			echo '<td class="optionbox" colspan="1">Box width
					<input type="number" value="200" name="box_width_' . $s . '"></td>';
		}
		echo '<td class="optionbox" colspan="1">Box width
				<input type="number" value="50" name="box_width_family"></td></tr>';
		
		// Default figure
		echo '<tr><td class="descriptionbox width30 wrap" rowspan="1">', I18N::translate ( 
				'Default portrait' ), '</td>';
		foreach(array("male", "female", "unknown") as $s) {
			echo '<td class="optionbox" colspan="1">
					<input type="text" size="30" value="silhouette_' . $s . '_small.png" name="default_portrait_' . $s . '"></td>';
		}
		
		echo '<td class="optionbox" colspan="1"/></tr>';
		
		// Edge line width
		echo '<tr><td class="descriptionbox width30 wrap">', I18N::translate ( 
				'Line width of edge' ), '</td><td class="optionbox" colspan="4">
			<input type="number" value="2" name="edge_line_width" min="1" max="7"></td></tr>';
				
		// Submit button
		echo '<tr><td class="topbottombar" colspan="6">', '<button>', I18N::translate ( 
				'Export' ), '</td></tr>
		</table></form></div>';
	}
	
	/**
	 * Format name
	 *
	 * @param Individual $record        	       	
	 * @param string $format        	
	 * @return string
	 */
	private function getGivenName($record, $format) {
		$tmp = $record->getAllNames ();
		$givn = $tmp [$record->getPrimaryName ()] ['givn'];
		// $surn = $tmp [$record->getPrimaryName ()] ['surname'];
		
		if ($givn && $format) {	
			$exp_givn = explode ( ' ', $givn );
			$count_givn = count ( $exp_givn );
			$exp_format = explode ( ",", $format );
			$givn = "";
			
			for ($i=0; $i < $count_givn; $i++) {
				$s = (string) $i+1;
				if (in_array($s,$exp_format)) {
					$givn .= " " . $exp_givn[$i];
				} elseif (in_array(".",$exp_format) || in_array($i . ".",$exp_format)) {
					$givn .= " " . $exp_givn[$i]{0} . "." ;
				}
			}
		}
	
		$givn = str_replace ( array ('@P.N.','@N.N.'), 
				array (I18N::translateContext ( 'Unknown given name', '…' ),
						I18N::translateContext ( 'Unknown surname', '…' ) 
				), trim($givn) );
		
		return $givn;
	}
	
	/**
	 * Format a place
	 *
	 * @param string $place        	
	 * @param string $format        	
	 * @return string
	 */
	private function formatPlace($place, $format) {
		$place_ret = "";
		if (is_object($place) && get_class($place) == "Fisharebest\Webtrees\Place") {	
			$place = $place->getGedcomName ();
		}
		if ($place) {
			// $place = strip_tags ( $place );
			if (! $format) {
				$place_ret .= $place;
			} else {
				$format_place_level = explode ( ",", $format );
				$exp_place = explode ( ',', $place );
				$count_place = count ( $exp_place );
				foreach ( $format_place_level as $s ) {
					$i = (int) $s;
					if (abs($i) <= $count_place && $i != 0) {
						if ($place_ret != "") $place_ret .= ", ";
						if ($i > 0) {
							$place_ret .= trim($exp_place [$i - 1]);
						} else {
							$place_ret .= trim($exp_place [$count_place + $i]);
						}
					}
				}
			}
		}
		return $place_ret;
	}
	
	/**
	 * Format a date
	 *
	 * @param Date $date        	
	 * @param string $format        	
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
	 * Get portrait
	 *
	 * @param Individual $record        	
	 * @return string
	 */
	private function getPortrait($record) {
		$portrait_file = "";
		$portrait = $record->findHighlightedMedia ();
		
		if ($portrait) {
			$portrait_file = $portrait->getFilename ();
		}

		return $portrait_file;
	}
	
	/**
	 * Get occupation
	 *
	 * @param Individual $record
	 * @param string $fact        	
	 * @param string $format        	
	 * @return string
	 */
	private function getFact($record, $fact, $format) {
		// get occupation
		$fact_string = "";
		$Facts = $record->getFacts ( $fact , true);
		/*$date = null;
		//$sortFacts = Functions::sortFacts($Facts);
		foreach ( $Facts as $Fact ) {
			$Fact_date = $Fact->getDate ();
			if (! $fact_string) {
				$fact_string = $Fact->getValue ();
				if ($Fact_date->isOK ())
					$date = $Fact_date;
			} elseif ($Fact_date->isOK ()) {
				if ($date) {
					// if (Date::compare($date->maximumDate(),$OCCU_date->maximumDate()) > 0) {
					if ($date->maximumDate ()->maxJD <
							 $Fact_date->maximumDate ()->maxJD) {
						$date = $Fact_date;
						$fact_string = $Fact->getValue ();
					}
				} else {
					$date = $Fact_date;
					$fact_string = $Fact->getValue ();
				}
			}
		}*/
		if ($Facts) {
			if (! $format) {
				foreach ($Facts as $Fact) {
					if ($fact_string != "") $fact_string .= $fact_string;
					$fact_string .= $Fact->getValue ();
				}
			} else {
				$exp_format = explode ( ",", $format );
				$count_facts = count ($Facts);
				$fact_list = array();
				foreach ( $exp_format as $s ) {
					$i = (int) $s;
					if (abs($i) <= $count_facts && $i != 0) {							
						if ($i > 0) {
							$j = $i -1;
						} else {
							$j = $count_facts + $i;
						}
						$fact_value = trim($Facts [$j]->getValue ());
						if (!in_array($fact_value, $fact_list)) {
							if ($fact_string != "") $fact_string .= $fact_string;
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
	 * @return String
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
	 * #@return String
	 */
	private function graphmlFooter() {
		return '<data key="d0"> <y:Resources/> </data>' . "\n" .
				 '</graph> </graphml>';
	}
	
	/**
	 * Split tempalte into components
	 *
	 * @param String $template        	
	 * @return Array
	 */
	private function splitTemplate($template) {
		if (strlen ( $template ) > 2) {
			$tag = $template {0};
			$format = $template {1};
			
			// remove line breaks
			$template = trim ( preg_replace ( '/\s+/', ' ', $template ) );
			
			// start with <html>
			$template_array = array (
					array ("component" => '<html>',"type" => 'string',
							"format" => "" , "fact" => ""
					) 
			);
			
			$i = 1;
			$pos_end = 1;
			$pos = 0;
			
			// now handle all tags
			while ( $pos !== false ) {
				$pos = strpos ( $template, $tag, $pos_end + 1 );
				if ($pos === false) {
					// check for a terminating string
					if ($pos_end + 1 < strlen ( $template )) {
						$template_array [$i] = array (
								"component" => substr ( $template, 
										$pos_end + 1 ),"type" => "string",
								"format" => "" , "fact" => ""
						);
						$i ++;
					}
				} else {
					
					if ($pos > $pos_end + 1) {
						// add a string
						$template_array [$i] = array (
								"component" => substr ( $template, 
										$pos_end + 1, $pos - $pos_end - 1 ),
								"type" => "string","format" => "" , "fact" => ""
						);
						$i ++;
					}
					
					// search for the end of the tag
					$pos_end = strpos ( $template, $tag, $pos + 1 );
					
					if ($pos_end !== false) {
						$pos_format = strpos ( $template, $format, $pos );
						
						if ($pos_format < $pos_end && $pos_format !== false) {
							$template_array [$i] = array (
									"component" => substr ( $template, 
											$pos + 1, $pos_format - $pos - 1 ),
									"type" => "tag",
									"format" => substr ( $template, 
											$pos_format + 1, 
											$pos_end - $pos_format - 1 ) , "fact" => ""
							);
							$i ++;
						} else {
							$template_array [$i] = array (
									"component" => substr ( $template, 
											$pos + 1, $pos_end - $pos - 1 ),
									"type" => "tag","format" => "" , "fact" => ""
							);
							$i ++;
						}
					}
				}
			}
			
			// end with </html>
			$template_array [$i] = array ("component" => '</html>',
					"type" => 'string',"format" => '', "fact" => ''
			);
		} else {
			$template_array = null;
		}
		// extract Fact
		for ($j=0; $j < $i; $j++) {
			$b = substr($template_array [$j]["component"], 0, 4);
			if (substr($template_array [$j]["component"], 0, 4) == "Fact") {
				$template_array [$j]["fact"] = substr($template_array [$j]["component"], 4);
				$template_array [$j]["component"] = "Fact";
			}
		}
		
		return $template_array;
	}
	
	/**
	 * Export the data in graphml format
	 *
	 * @param Tree $tree
	 *        	Which tree to export
	 * @param resource $gedout
	 *        	Handle to a writable stream
	 */
	private function exportGraphml(Tree $tree, $gedout) {
		
		// define the access level
		// $access_level = Auth::accessLevel ( $tree, Auth::user () );
		
		// get parameter entered in the form defined in set_parameter()
		$parameter = $_GET;
		
		// Split templates
		$template ["label"] = $this->splitTemplate ( 
				$parameter ["ind_label_template"] );
		$template ["description"] = $this->splitTemplate ( 
				$parameter ["ind_description_template"] );
		
		// Get header.
		// Buffer the output. Lots of small fwrite() calls can be very slow when writing large files.
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
			
			$sex = $record->getSex ();
			if ($sex == "F") {
				$col = $parameter ['color_female'];
				$node_style = $parameter ['node_style_female'];
				$col_border = $parameter ['border_female'];
				$box_width = $parameter ['box_width_female'];
				$portrait_fallback = $parameter ['default_portrait_female'];
			} elseif ($sex == "M") {
				$col = $parameter ['color_male'];
				$node_style = $parameter ['node_style_male'];
				$col_border = $parameter ['border_male'];
				$box_width = $parameter ['box_width_male'];
				$portrait_fallback = $parameter ['default_portrait_male'];
			} else {
				$col = $parameter ['color_unknown'];
				$node_style = $parameter ['node_style_unknown'];
				$col_border = $parameter ['border_unknown'];
				$box_width = $parameter ['box_width_unknown'];
				$portrait_fallback = $parameter ['default_portrait_unknown'];
			}
			
			foreach ( array ("label","description" 
			) as $a ) {
				
				// Fill template in three steps
				// Replace all tags @..$..@
				// Check for brackets {...} without replacement and remove these
				
				// replace @..$..@
				
				// loop over template array
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
								case "GivenName" :
									$tag_replacement .= $this->getGivenName ( 
											$record, $format );
									break;
								case "SurName" :
									$tag_replacement .= $record->getAllNames () [$record->getPrimaryName ()] ['surname'];
									$tag_replacement = str_replace ( '@N.N.',
											I18N::translateContext ( 'Unknown surname', '…' ), 
										 	$tag_replacement );
									break;
								case "BirthDate" :
									$tag_replacement .= $this->formatDate ( 
											$record->getBirthDate (), $format );
									break;
								case "BirthPlace" :
									$tag_replacement .= $this->formatPlace ( 
											$record->getBirthPlace (), $format );
									break;
								case "DeathDate" :
									$tag_replacement .= $this->formatDate ( 
											$record->getDeathDate (), $format );
									break;
								case "DeathPlace" :
									$tag_replacement .= $this->formatPlace ( 
											$record->getDeathPlace (), $format );
									break;
								case "MarriageDate" :
									$tag_replacement .= $this->formatDate ( 
											$record->getMarriageDate (), $format );
									break;
								case "MarriagePlace" :
									$tag_replacement .= $this->formatPlace ( 
											$record->getMarriagePlace (), 
											$format );
									break;
								case "Fact" :
									$tag_replacement .= $this->getFact ( 
											$record, $comp ["fact"], $format );
									break;
								case "Portrait" :
									$tag_replacement .= $this->getPortrait($record);
									If ($format == "fallback" && $tag_replacement == "") $tag_replacement = $portrait_fallback;
									break;
							}
							if ($tag_replacement != "") {
								// check for a {...} in $new_string and remove it
								$new_string = preg_replace ( "/{.*}/", "", 
										$new_string );
								$nodetext [$a] .= $new_string . $tag_replacement;
								$new_string = "";
							}
						}
					}
				}
				
				$new_string = preg_replace ( "/{.*}/", "", $new_string );
				$nodetext [$a] .= $new_string;
				$nodetext [$a] = preg_replace ( array ("/{/","/}/" 
				), array ("","" 
				), $nodetext [$a] );
				$nodetext [$a] = preg_replace ( "/<html><\/html>/", "", $nodetext [$a] );
				
				// portrait
				// $nodetext[$a] .= $this->getPortrait($record, in_array("portrait", $data[$a]), $parameter ['image_directory']);
				// name
				// $nodetext[$a] .= $this->getIndName($record, in_array("name", $data[$a]), $parameter[$a . "_format_name"]);
			}
			
			// this replacement has to be done for "lable"
			// for "description" no replacement must be done
			$nodetext ["label"] = str_replace ( "<", "&lt;", 
					$nodetext ["label"] );
			$nodetext ["label"] = str_replace ( ">", "&gt;", 
					$nodetext ["label"] );
			
			$label_rows = count ( explode ( "&lt;br&gt;", $nodetext ["label"] ) ) +
					 1;
			// create node
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
					 '" type="line" width="1.0"/> <y:NodeLabel alignment="center" autoSizePolicy="content" hasBackgroundColor="false" hasLineColor="false" textColor="#000000" fontFamily="Dialog" fontSize="12" fontStyle="plain" visible="true" modelName="internal" modelPosition="l" width="129" height="19" x="1" y="1">';
			
			// no line break befor $nodetext allowed
			$buffer .= $nodetext ["label"] . "\n" .
					 '</y:NodeLabel> </y:GenericNode> </data>' . "\n" .
					 "</node>\n";
			
			// write to file
			if (strlen ( $buffer ) > 65536) {
				fwrite ( $gedout, $buffer );
				$buffer = '';
			}
		}
		
		/*
		 * Create nodes for families
		 */
		// Split templates
		$template ["label"] = $this->splitTemplate ( 
				$parameter ["family_label_template"] );
		$template ["description"] = $this->splitTemplate ( 
				$parameter ["family_description_template"] );
		// Get all families
		$rows = Database::prepare ( 
				"SELECT f_id AS xref, f_gedcom AS gedcom" .
						 " FROM `##families` WHERE f_file = :tree_id ORDER BY f_id" )->execute ( 
				array ('tree_id' => $tree->getTreeId () 
				) )->fetchAll ();
		
		// loop over all families
		foreach ( $rows as $row ) {
			$record = Family::getInstance ( $row->xref, $tree );
			
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
										$tag_replacement .= $format;
									}
									break;
								case "MarriageDate" :
									$tag_replacement .= $this->formatDate ( 
											$record->getMarriageDate (), $format );
									break;
								case "MarriagePlace" :
									// does not work because no exception handling included
									// $tag_replacement .= $this->formatPlace($record->getMarriagePlace(), $format);
									$marriage = $record->getMarriage ();
									if ($marriage) {
										$tag_replacement .= $this->formatPlace ( 
												$marriage->getPlace (), $format );
									}
									break;
							}
							if ($tag_replacement != "") {
								// check for a {...} in $new_string and remove it
								$new_string = preg_replace ( "/{.*}/", "", 
										$new_string );
								$nodetext [$a] .= $new_string . $tag_replacement;
								$new_string = "";
							}
						}
					}
				}
				$new_string = preg_replace ( "/{.*}/", "", $new_string );
				$nodetext [$a] .= $new_string;
				$nodetext [$a] = preg_replace ( array ("/{/","/}/" 
				), array ("","" 
				), $nodetext [$a] );
				$nodetext [$a] = preg_replace ( "/<html><\/html>/", "", $nodetext [$a] );
				
				// $nodetext[$a] = str_replace ( "<", "&lt;", $nodetext[$a] );
				// $nodetext[$a] = str_replace ( ">", "&gt;", $nodetext[$a] );
			}
			// for lable <> must be replaced
			$nodetext ["label"] = str_replace ( "<", "&lt;", 
					$nodetext ["label"] );
			$nodetext ["label"] = str_replace ( ">", "&gt;", 
					$nodetext ["label"] );
			
			$col = $parameter ['color_family'];
			$node_style = $parameter ['node_style_family'];
			$col_border = $parameter ['border_family'];
			$box_width = $parameter ['box_width_family'];
			$label_rows = count ( explode ( "&lt;br&gt;", $nodetext ["label"] ) ) +
				
			$buffer .= '<node id="' . $row->xref . '">' . "\n";
			
			$buffer .= '<data key="d1"><![CDATA[http://my.site.com/' . $row->xref .
					 '.html]]></data>' . "\n";
			$buffer .= '<data key="d2"><![CDATA[' . $nodetext ["description"] .
					 ']]></data>' . "\n";
			
			$buffer .= '<data key="d3"> <y:ShapeNode>' .
					 '<y:Geometry height="'. 
					 //(6 + 15 * $label_rows) . '" width="' .
					 //$box_width . '" x="28" y="28"/>' . 
					 1 . '" width="' .
					 1 . '" x="28" y="28"/>' . 
					 '<y:Fill color="#000000" color2="#000000" transparent="false"/>' .
					 //'<y:Fill color="' . $col . '" color2="#000000" transparent="false"/>' .
					 '<y:BorderStyle hasColor="false" type="line" width="1.0"/>' .
					 '<y:NodeLabel alignment="center" autoSizePolicy="content" ' .
					 'backgroundColor="' . $col . '" hasLineColor="true" ' . 'lineColor="' . $col_border . '" ' .
					 //'backgroundColor="#ffffff" hasLineColor="false" ' .
					 'textColor="#000000" fontFamily="Dialog" fontSize="12" ' .
					 'fontStyle="plain" visible="true" modelName="internal" modelPosition="c" ' .
					 //'width="77" height="34" x="10" y="10">';
					 'width="' . $box_width. '" height="' . (12 * $label_rows) . '" x="10" y="10">';
					
					 
			$buffer .= $nodetext ["label"];
			
			$buffer .= '</y:NodeLabel> <y:Shape type="' .
					 $node_style . '"/>' .
					 '</y:ShapeNode> </data>' . "\n" . "</node>\n";
					
			if (strlen ( $buffer ) > 65536) {
				fwrite ( $gedout, $buffer );
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
			
			// add parents
			$parents = array ($record->getHusband (),$record->getWife () 
			);
			
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
			
			// now add edges for children
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
			
			// $buffer .= self::reformatRecord ( $rec );
			if (strlen ( $buffer ) > 65536) {
				fwrite ( $gedout, $buffer );
				$buffer = '';
			}
		}
		
		// add footer
		$buffer .= $this->graphmlFooter ();
		fwrite ( $gedout, $buffer );
	}
}

return new ExportGraphmlModule ( __DIR__ );