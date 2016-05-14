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
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\Database;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Date;
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
		<tr><td class="topbottombar" colspan="3">', I18N::translate ( 
				'Export tree in graphml format' ), '</td></tr>';
		
		// 1,1:3 Node / Description
		echo '<td class="descriptionbox width30 wrap">', '</td>';
		echo '<td class="descriptionbox width30 wrap">', I18N::translate ( 
				'Node label' ), '</td>';
		echo '<td class="descriptionbox width30 wrap">', I18N::translate ( 
				'Node description' ), '</td>';

		
		// 2,1 Data
		echo '<tr><td class="descriptionbox width30 wrap" rowspan="1">', I18N::translate (
				'Individual template' ), '</td>';
		
		// 2,2 Node label
		echo '<td class="optionbox">' .
				'<textarea rows="5" cols="50" name="ind_label_template">'
				. "{@GivenName&1@ }@SurName@ {(@BirthDate&%Y@-@DeathDate&%Y@)}<br>\n"
				.	'@Occupation@'
				. '</textarea></td>';
		
		// 2,3 Node label
		echo '<td class="optionbox">' .
				'<textarea rows="5" cols="50" name="ind_description_template">'
				. "{@GivenName@ }@Name&full@ {(@BirthDate&%Y@-@DeathDate&%Y@)}<br>\n"
				.	'@Occupation@'
				. '</textarea></td></tr>';
		
		// 3,1 Data
		echo '<tr><td class="descriptionbox width30 wrap" rowspan="1">', I18N::translate (
				'Family template' ), '</td>';
		
		// 3,2 Node label
		echo '<td class="optionbox">' .
				'<textarea rows="5" cols="50" name="family_label_template">'
				. ""
				. '</textarea></td>';
		
		// 3,2 Node label
		echo '<td class="optionbox">' .
				'<textarea rows="5" cols="50" name="family_description_template">'
				. "oo@MarriageDate&%j.%n.%Y@\n"
				. "{<br>@MarriagePlace&R2@)}"
				. '</textarea></td></tr>';
		
		// 4,1 Data
		echo '<tr><td class="descriptionbox width30 wrap" rowspan="1">', I18N::translate (
						'Keywords' ), '</td>';
				
		// 5,2 Node label
		echo '<td class="optionbox" colspan="2">' .
					'<table>'
					. '<td>@GivenName</td><td>Given Name'
					. '<tr><td>@SurName</td><td>Sur name'
					. '<tr><td>@BirthDate</td><td>Birth date'
					. '<tr><td>@BirthPlace</td><td>Birth place'
					. '<tr><td>@DeathDate</td><td>Death date'
					. '<tr><td>@DeathPlace</td><td>Death place'
					. '<tr><td>@MarriageDate</td><td>Marriage date'
					. '<tr><td>@MarriagePlace</td><td>Marriage place'
					. '<tr><td>@Occupation</td><td>Occupation'
							. '</table></td>';
				
				// 2,1 Data
		echo '<tr><td class="descriptionbox width30 wrap" rowspan="1">', I18N::translate ( 
				'Data shown' ), '</td>';
		
		// 2,2 Node label
		echo '<td class="optionbox">' .
				 '<select name="label_data[]" multiple="multiple" size=10>' .
				 '<option name="test" value="name" selected>Name</option>' .
				 '<option value="birth_date" selected>Birth date</option>' .
				 '<option value="death_date" selected>Death date</option>' .
				 '<option value="marriage_date">Marriage date</option>' .
				 '<option value="birth_place" selected>Birth place</option>' .
				 '<option value="death_place">Death place</option>' .
				 '<option value="marriage_place">Marriage place</option>' .
				 '<option value="occupation" selected>Occupation</option>' .
				 '<option value="portrait" selected>Portrait</option>' .
				 '<option value="silhouette" selected>Silhouette</option>' .
				 '</select></td>';
		
		// 2,3 Node description
		echo '<td class="optionbox">' .
				 '<select name="description_data[]" multiple="multiple" size=8>' .
				 '<option value="name" selected>Name</option>' .
				 '<option value="birth_date" selected>Birth date</option>' .
				 '<option value="death_date" selected>Death date</option>' .
				 '<option value="marriage_date" selected>Marriage date</option>' .
				 '<option value="birth_place" selected>Birth place</option>' .
				 '<option value="death_place" selected>Death place</option>' .
				 '<option value="marriage_place" selected>Marriage place</option>' .
				 '<option value="occupation" selected>Occupation</option>' .
				 '</select></td></tr>';
		
		// 3,1 Name Format
		echo '<tr><td class="descriptionbox width30 wrap" rowspan="1">', I18N::translate ( 
				'Name format' ), '</td>';
		
		// 3,2 Label
		echo '<td class="optionbox">' . '<select name="label_format_name">' .
				 '<option value="full">All given name</option>' .
				 '<option value="1" selected>First given name</option>' .
				 '<option value="2">First two given names</option></select></td>';
		
		// 3,3 Description
		echo '<td class="optionbox">' . '<select name="description_format_name">' .
				 '<option value="full" selected>All given name</option>' .
				 '<option value="1">First given name</option>' .
				 '<option value="2">First two given names</option></select></td></tr>';
		
		// 4,1 Date Format
		echo '<tr><td class="descriptionbox width30 wrap" rowspan="1">', I18N::translate ( 
				'Date format' ), '</td>';
		
		// 4,2 Label
		echo '<td class="optionbox">' . '<select name="label_format_dates">' .
				 '<option value="%j.%n.%Y">day.month.year</option>' .
				 '<option value="%n/%j/%Y">month/day/year</option>' .
				 '<option value="%Y" selected>Year only</option></select></td>';
		
		// 4,3 Description
		echo '<td class="optionbox">' .
				 '<select name="description_format_dates">' .
				 '<option value="%j.%n.%Y" selected>day.month.year</option>' .
				 '<option value="%n/%j/%Y">month/day/year</option>' .
				 '<option value="%Y">Year only</option></select></td></tr>';
		
		// 4,1 Date Position
		echo '<tr><td class="descriptionbox width30 wrap" rowspan="1">', I18N::translate ( 
				'Birth and death date position' ), '</td>';
		
		// 4,2 Label
		echo '<td class="optionbox">' . '<select name="label_position_dates">' .
				 '<option value="after" selected>After name</option>' .
				 '<option value="below">Below name</option></select></td>';
		
		// 4,3 Description
		echo '<td class="optionbox">' .
				 '<select name="description_position_dates">' .
				 '<option value="after">After name</option>' .
				 '<option value="below" selected>Below name</option></select></td>';
				 
		// 5,1 Place Format
		echo '<tr><td class="descriptionbox width30 wrap" rowspan="3">', I18N::translate ( 
				'Place format' ), '</td>';
		
		// 5,2 Label Places
		echo '<td class="optionbox">' . '<select name="label_format_place">' .
				 '<option value="full">Full hierarchy</option>' .
				 '<option value="partial" selected>Selected levels</option></select></td>';
		
		// 5,3 Description Places
		echo '<td class="optionbox">' .
				 '<select name="description_format_place">' .
				 '<option value="full" selected>Full hierarchy</option>' .
				 '<option value="partial">Selected levels</option></select></td></tr>';
		
		// 6,2 Label Places selection
		echo '<tr><td class="optionbox">Hierarchy levels<br>' .
				 '<select name="label_format_place_levels[]" multiple="multiple" size=6>' .
				 '<option value="1">Level 1</option>' .
				 '<option value="2" selected>Level 2</option>' .
				 '<option value="3">Level 3</option>' .
				 '<option value="4">Level 4</option>' .
				 '<option value="5">Level 5</option>' .
				 '<option value="6">Level 6</option>' . '</select></td>';
		
		// 6,3 Label Places selection
		echo '<td class="optionbox">Hierarchy levels<br>' .
				 '<select name="description_format_place_levels[]" multiple="multiple" size=6>' .
				 '<option value="1">Level 1</option>' .
				 '<option value="2">Level 2</option>' .
				 '<option value="3">Level 3</option>' .
				 '<option value="4">Level 4</option>' .
				 '<option value="5">Level 5</option>' .
				 '<option value="6">Level 6</option>' . '</select></td></tr>';
		
		// 7,2 Label places count from
		echo '<tr><td class="optionbox">' .
				 '<select name="label_format_place_count_from">' .
				 '<option value="right" selected>Count from right</option>' .
				 '<option value="left">Count from left</option></select></td>';
		
		// 7,3 Description Places count from
		echo '<td class="optionbox">' .
				 '<select name="description_format_place_count_from">' .
				 '<option value="right" selected>Count from right</option>' .
				 '<option value="left">Count from left</option></select></td></tr>';
		
		// 4,1 Occupation Format
		echo '<tr><td class="descriptionbox width30 wrap" rowspan="1">', I18N::translate ( 
				'Occupation format' ), '</td>';
		
		// 4,2 Label
		echo '<td class="optionbox">' . '<select name="label_format_occupation">' .
				 '<option value="%j.%n.%Y">day.month.year</option>' .
				 '<option value="%n/%j/%Y">month/day/year</option>' .
				 '<option value="%Y" selected>Year only</option></select></td>';
		
		// 4,3 Description
		echo '<td class="optionbox">' .
				 '<select name="description_format_occupation">' .
				 '<option value="%j.%n.%Y" selected>day.month.year</option>' .
				 '<option value="%n/%j/%Y">month/day/year</option>' .
				 '<option value="%Y">Year only</option></select></td></tr>';
		
		// 8:10,1 Box style
		echo '<td class="descriptionbox width30 wrap" rowspan="4">', I18N::translate ( 
				'Box style' ), '</td>';
		
		// 8:2:3 male/female
		echo '<td class="descriptionbox width30 wrap">', I18N::translate ( 
				'Male' ), '</td>';
		echo '<td class="descriptionbox width30 wrap">', I18N::translate ( 
				'Female' ), '</td></tr>';
		
		// 9,2 Box style male
		echo '<tr><td class="optionbox">' .
				 '<select name="node_style_male" value="BevelNode2">' .
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
		
		// 9,3 Box style female
		echo '<td class="optionbox">' .
				 '<select name="node_style_female" value="BevelNode2">' .
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
				 '</select></td></tr>';
		
		// 10,2:3 Fill color
		echo '<tr><td class="optionbox">Fill color 
				<input type="color" value="#d9ffff" name="color_male"></td>';
		echo '<td class="optionbox">Fill color 
				<input type="color" value="#ffd7ff" name="color_female"></td></tr>';
		
		// 11,2:3 Border color
		echo '<tr><td class="optionbox">Border color
				<input type="color" value="#c0c0c0" name="border_male"></td>';
		echo '<td class="optionbox">Border color
				<input type="color" value="#c0c0c0" name="border_female"></td></tr>';
		
		// 12,1:3 Edge line width
		echo '<tr><td class="descriptionbox width30 wrap">', I18N::translate ( 
				'Line width of edge' ), '</td><td class="optionbox" colspan="2">
			<input type="number" value="2" name="edge_line_width" min="1" max="7"></td></tr>';
		
		// 13,1:3 Path to media directory
		echo '<tr><td class="descriptionbox width30 wrap">', I18N::translate ( 
				'Path to media directory' ), '</td><td class="optionbox" colspan="2">
				<input type="text" value="C:/xampp/htdocs/webtrees/data/media/thumbs" name="image_directory"></td></tr>';
		
		// submit button
		echo '<tr><td class="topbottombar" colspan="3">', '<button>', I18N::translate ( 
				'Export' ), '</td></tr>
		</table></form></div>';
	}
	
	/**
	 * Format name
	 *
	 * @param Individual $record        	
	 * @param string $show_name        	
	 * @param string $format_name        	
	 * @return string
	 */
	private function getIndName($record, $show_name, $format_name) {
		if (!$show_name) {
			$name = "";
		} else {
			$tmp = $record->getAllNames ();
			$givn = $tmp [$record->getPrimaryName ()] ['givn'];
			$surn = $tmp [$record->getPrimaryName ()] ['surname'];
			$new_givn = explode ( ' ', $givn );
			$count_givn = count ( $new_givn );
			
			if ($count_givn == 0) {
				$name = $surn;
			} elseif ($count_givn > 1 && $format_name == 2) {
				$name = $new_givn [0] . " " . $new_givn [1] . " " . $surn;
			} else {
				$name = $new_givn [0] . " " . $surn;
			}
			$name = str_replace ( array ('@P.N.','@N.N.' 
			), 
					array (
							I18N::translateContext ( 'Unknown given name', '…' ),
							I18N::translateContext ( 'Unknown surname', '…' ) 
					), $name );
		}
		
		return $name;
	}
	
	/**
	 * Format a place
	 *
	 * @param string $place        	
	 * @param string $show_places        	
	 * @param string $format_places        	
	 * @param string $format_place_level        	
	 * @param string $format_place_count_from        	
	 * @return string
	 */
	private function formatPlace($place, $show_places, $format_places, 
			$format_place_level, $format_place_count_from) {
		if (!$show_places || !$place) {
			$place_ret = "";
		} else {
			$place_ret = " ";
			$place = strip_tags ( $place );
			if ($format_places == "full") {
				$place_ret .= $place;
			} else {
				$exp_place = explode ( ',', $place );
				$count_place = count ( $exp_place );
				foreach ($format_place_level as $i) {
					if ($i <= $count_place) {		
						if ($format_place_count_from == "right") {
							$place_ret .= $exp_place [$i - 1];
						} else {
							$place_ret .= $exp_place [$count_place - $i];
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
	 * @param string $show_dates        	
	 * @param string $format_dates      	
	 * @return string
	 */
	private function formatDate($date, $show_date, $format_dates) {
		if (!$show_date) {
			$date_label = "";
		} else {
			if ($date instanceof Date) {
				$date_label = strip_tags ( 
						$date->display ( false, $format_dates ) );
			} else {
				$date_label = "";
			}
		}
		return $date_label;
	}
	
	/**
	 * Get portrait
	 *
	 * @param Individual $record        	
	 * @param string $get_portrait        	    	
	 * @return string
	 */
	private function getPortrait($record, $show_portrait, $directory) {
		$portrait_html = "";
		$portrait = $record->findHighlightedMedia ();
		
		if ($show_portrait && $portrait) {
			$portrait = $portrait->getFilename ();
			if ($portrait) {
				$portrait_html = '<td><img src="file:' . $directory . "/" .
						 $portrait
						.'" alt="kein Bild" width="20" height="30"><td>';
			}
		}
		
		return $portrait_html;
	}
	
	/**
	 * Get occupation
	 *
	 * @param Individual $record        	
	 * @param string $get_occupation        	    	
	 * @param string $format_occupation        	    	
	 * @return string
	 */
	private function getOccupation($record, $show_occupation, $format_occupation) {
		// get occupation
		$occupation = "";
		if ($show_occupation) {
			$OCCUs = $record->getFacts ( "OCCU" );
			$date = null;
			foreach ( $OCCUs as $OCCU ) {
				$OCCU_date = $OCCU->getDate ();
				if (! $occupation) {
					$occupation = $OCCU->getValue ();
					if ($OCCU_date->isOK ())
						$date = $OCCU_date;
				} elseif ($OCCU_date->isOK ()) {
					if ($date) {
						// if (Date::compare($date->maximumDate(),$OCCU_date->maximumDate()) > 0) {
						if ($date->maximumDate ()->maxJD <
								$OCCU_date->maximumDate ()->maxJD) {
									$date = $OCCU_date;
									$occupation = $OCCU->getValue ();
								}
					} else {
						$date = $OCCU_date;
						$occupation = $OCCU->getValue ();
					}
				}
			}
		}

		if ($occupation != "") $occupation = '<br>' . $occupation;
		
		return $occupation;
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
				 "\n" . '<key for="edge" id="d6" yfiles.type="edgegraphics"/>' .
				 "\n" . '<graph edgedefault="directed" id="G">' . "\n";
	}
	
	/**
	 * Return the footer for the graphml file
	 *
	 * @return String
	 */
	private function graphmlFooter() {
		return '<data key="d0"> <y:Resources/> </data>' . "\n" .
				 '</graph> </graphml>';
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
		
		foreach(array("label", "description") as $a) {
			if (array_key_exists($a . "_data", $parameter)) {
				$data[$a] = $parameter[$a . "_data"];
			} else {
				$data[$a] = array();
			}
			if (array_key_exists($a . "_format_place_levels", $parameter)) {
				$format_place_levels[$a] = $parameter[$a . "_format_place_levels"];
			} else {
				$format_place_levels[$a] = array();
			}
		}
		
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
			} elseif ($sex == "M") {
				$col = $parameter ['color_male'];
				$node_style = $parameter ['node_style_male'];
				$col_border = $parameter ['border_male'];
			} else {
				$col = "#CCCCFF";
				$node_style = $parameter ['node_style_male'];
				$col_border = $parameter ['border_male'];
			}
			
			
			
			foreach(array("label", "description") as $a) {
							
				$nodetext[$a] = '<html><table>';
					
				// portrait
				$nodetext[$a] .= $this->getPortrait($record, in_array("portrait", $data[$a]), $parameter ['image_directory']);
				// name
				$nodetext[$a] .= $this->getIndName($record, in_array("name", $data[$a]), $parameter[$a . "_format_name"]);
					
				// birth date and place
				$date_birth = $this->formatDate($record->getBirthDate (),
						in_array("birth_date", $data[$a]), $parameter[$a . "_format_dates"], '*');

				$place_birth = $this->formatPlace($record->getBirthPlace (),
						in_array("birth_place", $data[$a]),
						$parameter[$a . "_format_place"],
						$format_place_levels[$a],
						$parameter[$a . "_format_place_count_from"]);
			
				// death date and place
				$date_death = $this->formatDate($record->getDeathDate (),
						in_array("death_date", $data[$a]), $parameter[$a . "_format_dates"], '+');
				$place_death = $this->formatPlace($record->getDeathPlace (),
						in_array("death_place", $data[$a]),
						$parameter[$a . "_format_place"],
						$format_place_levels[$a],
						$parameter[$a . "_format_place_count_from"]);
				
				if ($parameter[$a . "_position_dates"] == "after") {
					if ($date_birth . $date_death != "") {
						$nodetext[$a] .= " (" . $date_birth . "-" . $date_death . ")";
					}
					if ($place_birth) $nodetext[$a] .= "<br>* " . $place_birth;
					if ($place_death) $nodetext[$a] .= "<br>+ " . $place_death;					
				} else {
					if ($date_birth . $place_birth != "") {
						$nodetext[$a] .= "<br>* " . $date_birth . $place_birth;
					}
					if ($date_death . $place_death != "") {
						$nodetext[$a] .= "<br>+ " . $date_death . $place_death;
					}
				}
				
				
				// occupancy
				$nodetext[$a] .= $this->getOccupation($record, in_array("occupation", $data[$a]), $parameter[$a . "_format_occupation"]);
					
				$nodetext[$a] .= '</table></html>';
				
			}
			$nodetext["label"] = str_replace ( "<", "&lt;", $nodetext["label"] );
			$nodetext["label"] = str_replace ( ">", "&gt;", $nodetext["label"] );
				
			$label_rows = count ( explode ( "&lt;br&gt;", $nodetext["label"] ) ) + 1;
			// create node
			$buffer .= '<node id="' . $row->xref . '">' . "\n" 
					 . '<data key="d1"><![CDATA[http://my.site.com/' . $row->xref 
					 . '.html]]></data>' . "\n"
					 . '<data key="d2"><![CDATA['
					 . $nodetext["description"] 
					 . ']]></data>' . "\n" 
					 . '<data key="d3">' 
					 . '<y:GenericNode configuration="' . $node_style 
					 . '"> <y:Geometry height="' . (6 + 15 * $label_rows) 
					 . '" width="130" x="10" y="10"/> <y:Fill color="' . $col 
					 . '" transparent="false"/> <y:BorderStyle color="' 
					 . $col_border 
					 . '" type="line" width="1.0"/> <y:NodeLabel alignment="center" autoSizePolicy="content" hasBackgroundColor="false" hasLineColor="false" textColor="#000000" fontFamily="Dialog" fontSize="12" fontStyle="plain" visible="true" modelName="internal" modelPosition="c" width="129" height="19" x="1" y="1">';
			
			// no line break befor $nodetext allowed
			$buffer .= $nodetext["label"] . "\n" .
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
		// Get all families
		$rows = Database::prepare ( 
				"SELECT f_id AS xref, f_gedcom AS gedcom" .
						 " FROM `##families` WHERE f_file = :tree_id ORDER BY f_id" )->execute ( 
				array ('tree_id' => $tree->getTreeId () 
				) )->fetchAll ();
		
		// loop over all families
		foreach ( $rows as $row ) {
			$record = Family::getInstance ( $row->xref, $tree );
			
			// marriage date
			foreach(array("label", "description") as $a) {
				if ($record->getMarriage()) {				
					$nodetext[$a] = $this->formatDate($record->getMarriageDate (),
					 		in_array("marriage_date", $data[$a]), $parameter["label_format_dates"], 'oo');
					
					$b = $record->getMarriagePlace ()->getFullName() ;
					
					$nodetext[$a] .= $this->formatPlace($record->getMarriagePlace ()->getFullName() ,
					 		in_array("marriage_place", $data[$a]),
					 		$parameter["label_format_place"],
					 		$format_place_levels[$a],
					 		$parameter["label_format_place_count_from"]);
				} else {
					$nodetext[$a] = "";
				}
			}
			
			$buffer .= '<node id="' . $row->xref . '">' . "\n";
			
			$buffer .= '<data key="d1"><![CDATA[http://my.site.com/' . $row->xref .
					 '.html]]></data>';
			$buffer .= '<data key="d2"><![CDATA[' 
					. $nodetext["description"]
					.']]></data>' . "\n";
			
			$buffer .= '<data key="d3"> <y:ShapeNode>' .
					 '<y:Geometry height="1.0" width="' .
					 $parameter ['edge_line_width'] . '" x="28" y="28"/>' . // '" x="28" y="28"/>'
					 '<y:Fill color="#000000" color2="#000000" transparent="false"/>' .
					 '<y:BorderStyle hasColor="false" type="line" width="1.0"/>' .
					 '<y:NodeLabel alignment="center" autoSizePolicy="content" backgroundColor="#ffffff" hasLineColor="false" textColor="#000000" fontFamily="Dialog" fontSize="12" fontStyle="plain" visible="true" modelName="internal" modelPosition="c" width="77" height="34" x="10" y="10">';
			
					 
			$buffer .= $nodetext["label"];
			
			$buffer .= '</y:NodeLabel> <y:Shape type="rectangle"/>' .
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
							 '">' . "\n";
					
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