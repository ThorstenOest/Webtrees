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
 *  
 */
class ExportGraphmlModule extends AbstractModule implements ModuleReportInterface {
	/**
	 * Return a menu item for this report.
	 * When selected it called the function modAction with parameter
	 * mod_action=set_parameter.
	 *
	 * @return Menu
	 */
	public function getReportMenu() {
		return new Menu ( $this->getTitle (), 'module.php?mod=' . $this->getName () . '&amp;mod_action=set_parameter', 'menu-report-' . $this->getName (), array (
				'rel' => 'nofollow' 
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
	 * A sentence describing what this module does. This text appears on the 
	 * admin pages where the module can be activeated.
	 *
	 * @return string
	 */
	public function getDescription() {		// This text also appears in the .XML file - update both together
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
	 * @param string $mod_action = 'set_parameter'
	 * opens a form to get parameter for the export
	 * @param string $mod_action = 'export' writes the grapgml file
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
				if (strtolower ( substr ( $download_filename, - 8, 8 ) ) != '.graphml') {
					$download_filename .= '.graphml';
				}
								
				// Stream the file straight to the browser.
				header ( 'Content-Type: text/plain; charset=UTF-8' );
				header ( 'Content-Disposition: attachment; filename="' . $download_filename . '"' );
				$stream = fopen ( 'php://output', 'w' );
				$this->exportGraphml ( $WT_TREE, $stream);
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
	 *
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
		<tr><td class="topbottombar" colspan="3">', I18N::translate ( 'Export tree in graphml format' ), '</td></tr>';
	
		// * Name
		echo	'<tr><td class="descriptionbox width30 wrap" rowspan="1">', I18N::translate ('Name'), '</td>';

		// ** Given name
		echo	'<td class="descriptionbox width30 wrap">', I18N::translate ('Given name'), '</td><td class="optionbox">'
				. '<select name="include_given_name">'
				. '<option value="full_name">Full name</option>'
				. '<option value="1">First name</option>'
				. '<option value="2">First two name</option></select></td></tr>';
		
		// * Birth and dead
		echo	'<tr><td class="descriptionbox width30 wrap" rowspan="3">', I18N::translate ('Birth and death'), '</td>';

		// ** Dates for birth and death
		echo	'<td class="descriptionbox width30 wrap">', I18N::translate ('Include dates'), '</td><td class="optionbox">'
				. '<select name="include_dates_birth_death">'
				. '<option value="no">No date</option>'
				. '<option value="full_date">Full date</option>'
				. '<option value="year">Year only</option></select></td></tr>';
				
		
		// ** Places for birth/death 
		echo	'<td class="descriptionbox width30 wrap">', I18N::translate ('Include places'), '</td><td class="optionbox">'
				. '<select name="include_places_birth_death">'
				. '<option value="no">No place</option>'
				. '<option value="full_place">Full place</option>'
				. '<option value="from_left">From left</option>'
				. '<option value="from_right">From right</option></select></td></tr>';
				
		echo '<td class="descriptionbox width30 wrap">', I18N::translate ('Position in place'), '</td><td class="optionbox">
			<input type="number" value="1" name="include_place_position" min="1" max="5"></td></tr>';
				
		// * Occupation
		echo	'<tr><td class="descriptionbox width30 wrap" rowspan="1">', I18N::translate ('Occupation'), '</td>';

		echo '<td class="descriptionbox width30 wrap">', I18N::translate ('Show occupation'), '</td><td class="optionbox">
				<input type="checkbox" value="1" name="include_occupation"></td></tr>';
		
		// * Image
		echo	'<tr><td class="descriptionbox width30 wrap" rowspan="3">', I18N::translate ('Image'), '</td>';

		echo '<td class="descriptionbox width30 wrap">', I18N::translate ('Show silhouette'), '</td><td class="optionbox">
				<input type="checkbox" value="1" name="include_silhouette"></td></tr>';
		echo '<td class="descriptionbox width30 wrap">', I18N::translate ('Show image'), '</td><td class="optionbox">
				<input type="checkbox" value="1" name="include_image"></td></tr>';
		echo '<td class="descriptionbox width30 wrap">', I18N::translate ('Local media directory'), '</td><td class="optionbox">
				<input type="text" value="C:/xampp/htdocs/webtrees/data/media/thumbs" name="image_directory"></td></tr>';
		
		// * Marriage
		echo	'<tr><td class="descriptionbox width30 wrap" rowspan="1">', I18N::translate ('Marriage'), '</td>';

		// ** Dates for marriage
		echo	'<td class="descriptionbox width30 wrap">', I18N::translate ('Show dates'), '</td><td class="optionbox">'
				. '<select name="include_dates_marriage">'
				. '<option value="no">No date</option>'
				. '<option value="full_date">Full date</option>'
				. '<option value="year">Year only</option></select></td></tr>';
	
		// * Format
		echo	'<tr><td class="descriptionbox width30 wrap" rowspan="5">', I18N::translate ('Format'), '</td>';

		// ** Edge line width
		echo '<td class="descriptionbox width30 wrap">', I18N::translate ('Line width of edge'), '</td><td class="optionbox">
			<input type="number" value="2" name="edge_line_width" min="1" max="7"></td></tr>';

		// ** Box style
		echo	'<td class="descriptionbox width30 wrap">', I18N::translate ('Box style'), '</td><td class="optionbox">'
				. '<select name="node_style">'
				. '<option value="BevelNode2">BevelNode2</option>'
				. '<option value="BevelNode">BevelNode</option>'
				. '<option value="BevelNodeWithShadow">BevelNodeWithShadow</option>'
				. '<option value="BevelNode3">BevelNode3</option>'
				. '<option value="ShinyPlateNode">ShinyPlateNode</option>'
				. '<option value="ShinyPlateNodeWithShadow">ShinyPlateNodeWithShadow</option>'
				. '<option value="ShinyPlateNode2">ShinyPlateNode2</option>'
				. '<option value="ShinyPlateNode3">ShinyPlateNode3</option>'
				. '</select></td></tr>';
		
		// * Color 
		echo '<td class="descriptionbox width30 wrap">', I18N::translate ('Color Male'), '</td><td class="optionbox">
				<input type="color" value="#d9ffff" name="color_male"></td></tr>';
		echo '<td class="descriptionbox width30 wrap">', I18N::translate ('Color Female'), '</td><td class="optionbox">
				<input type="color" value="#ffd7ff" name="color_female"></td></tr>';
		echo '<td class="descriptionbox width30 wrap">', I18N::translate ('Color Frame'), '</td><td class="optionbox">
				<input type="color" value="#c0c0c0" name="color_frame"></td></tr>';
		
		// submit button
		echo '<tr><td class="topbottombar" colspan="3">', '<button>', I18N::translate ( 'Export' ), '</td></tr>
		</table></form></div>';
	}
	
	/**
	 * Return the header for the graphml file
	 *
	 * @return String
	 */
	private function graphmlHeader() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . "\n" . 
				'<graphml xmlns="http://graphml.graphdrawing.org/xmlns" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:y="http://www.yworks.com/xml/graphml" xsi:schemaLocation="http://graphml.graphdrawing.org/xmlns http://www.yworks.com/xml/schema/graphml/1.1/ygraphml.xsd">' . "\n" . 
				'<!--Created by Webtrees -->' . "\n" .
				'<key for="graphml" id="d0" yfiles.type="resources"/>' . "\n" . 
				'<key for="node" id="d1" attr.name="url" attr.type="string"/>' . "\n" . 
				'<key for="node" id="d2" attr.name="description" attr.type="string"/>' . "\n" . 
				'<key for="node" id="d3" yfiles.type="nodegraphics"/>' . "\n" . 
				'<key for="edge" id="d4" attr.name="url" attr.type="string"/>' . "\n" .
				'<key for="edge" id="d5" attr.name="description" attr.type="string"/>' . "\n" .
				'<key for="edge" id="d6" yfiles.type="edgegraphics"/>' . "\n" .
				'<graph edgedefault="directed" id="G">' . "\n";
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
		$access_level = Auth::accessLevel($tree, Auth::user());

		// get parameter entered in the form defined in set_parameter()
		$include_given_name = Filter::get ( 'include_given_name' );
		$include_dates_birth_death = Filter::get ( 'include_dates_birth_death' );
		$include_places_birth_death = Filter::get ( 'include_places_birth_death' );
		$include_dates_marriage = Filter::get ( 'include_dates_marriage' );
		$include_place_position = Filter::get ( 'include_place_position' );
		$include_occupation = Filter::get ( 'include_occupation' );
		$edge_line_width = Filter::get ( 'edge_line_width' );
		$node_style = Filter::get ( 'node_style' );
		$color_male = Filter::get ( 'color_male' );
		$color_female = Filter::get ( 'color_female' );
		$color_frame = Filter::get ( 'color_frame' );
		$include_silhouette = Filter::get ( 'include_silhouette' );
		$include_image = Filter::get ( 'include_image' );
		$image_directory = Filter::get ( 'image_directory' );
		//
		$buffer = "";
		
		// Get header.  
		// Buffer the output. Lots of small fwrite() calls can be very slow when writing large files.
		$buffer = $this->graphmlHeader ();
			
		/*
		*  Create nodes for individuals
		*/
		// Get all individuals
		$rows = Database::prepare ( "SELECT i_id AS xref, i_gedcom AS gedcom" . " FROM `##individuals` WHERE i_file = :tree_id ORDER BY i_id" )->execute ( array (
				'tree_id' => $tree->getTreeId () 
		) )->fetchAll ();
		

		// loop over all individuals
		foreach ( $rows as $row ) {
			$record = Individual::getInstance ( $row->xref, $tree );
			
			// get name
			if ($include_given_name == "full_name") {
				$name = strip_tags ( $record->getFullName () );
			} else {		
				$tmp        = $record->getAllNames();
				$givn       = $tmp[$record->getPrimaryName()]['givn'];
				$surn       = $tmp[$record->getPrimaryName()]['surname'];
				$new_givn   = explode(' ', $givn);
				$count_givn = count($new_givn);

				if ($count_givn == 0) {
					$name = $surn;
				} elseif ($count_givn > 1 && $include_given_name == 2) {
					$name = $new_givn[0] . " " . $new_givn[1] . " " . $surn;
				} else {
					$name = $new_givn[0] . " " . $surn;				
				}
				$name = str_replace(
							array('@P.N.', '@N.N.'),
							array(I18N::translateContext('Unknown given name', '…'), I18N::translateContext('Unknown surname', '…')),
							$name
						);
			}
				
			
			
			// get dates
			$birth_date = null;
			$death_date = null;

			if ($include_dates_birth_death == "full_date") {
				$birth_date = $record->getBirthDate();
				$death_date = $record->getDeathDate();
				if ($birth_date instanceof Date) {
					$birth_date = strip_tags ($birth_date->display());
				}
				if ($death_date instanceof Date) {
					$death_date = strip_tags ($death_date->display());
				}
				//->format('Y-m-d');
			} elseif ($include_dates_birth_death == "year") {
				$birth_date = $record->getBirthYear();
				$death_date = $record->getDeathYear();
			}			

			
			$birth_place = null;
			$death_place = null;				
			if ($include_places_birth_death != "no") {
				$birth_place = strip_tags($record->getBirthPlace());
				$death_place = strip_tags($record->getDeathPlace());
				if ($include_places_birth_death != "full_place") {
					$exp_birth_place = explode(',', $birth_place);
					$exp_death_place = explode(',', $death_place);
					$count_birth_place = count($exp_birth_place);
					$count_death_place = count($exp_death_place);
					$pos_birth_place = min($include_place_position,count($exp_birth_place));
					$pos_death_place = min($include_place_position,count($exp_death_place));
						
					if ($include_places_birth_death != "from_left") {
						$birth_place = $exp_birth_place[$pos_birth_place - 1];
						$death_place = $exp_death_place[$pos_death_place - 1];
					} elseif ($include_places_birth_death != "from_right") {
						$birth_place = $exp_birth_place[$count_birth_place - $pos_birth_place];
						$death_place = $exp_death_place[$count_death_place - $pos_death_place];
					}
				}
			}
							
			$sex = $record->getSex();
			if ($sex == "F") {
				$col = $color_female;
			} elseif ($sex == "M") {
				$col = $color_male;
			} else {
				$col = "#CCCCFF";
			}
			
			// get image
			if ($include_image) {
				$image = $record->findHighlightedMedia();;
				if ($image) {
					$image = "file:" . $image_directory ."/". $image->getFilename();
				}
			} else {
				$image = null;
			}


			// get occupation
			$occupation = null;
			if ($include_occupation) {
				$OCCUs = $record->getFacts("OCCU");
				$date = null;
				foreach ($OCCUs as $OCCU) {
					$OCCU_date = $OCCU->getDate();
					if  (!$occupation) {
						$occupation = $OCCU->getValue();
						if ($OCCU_date->isOK()) $date = $OCCU_date;
					} elseif ($OCCU_date->isOK()) {
						if ($date) {
							//if (Date::compare($date->maximumDate(),$OCCU_date->maximumDate()) > 0) {
							if ($date->maximumDate()->maxJD < $OCCU_date->maximumDate()->maxJD) {
								$date = $OCCU_date;
								$occupation = $OCCU->getValue();
							}
						} else {
							$date = $OCCU_date;
							$occupation = $OCCU->getValue();
						}
					} 
				}
			}

			$nodetext = '<html><table>';
			
			// image
			if ($image) {
				$nodetext .= '<td><img src="' . $image . '" alt="kein Bild" width="20" height="30">';
				$nodetext .= '<td>';
			}
			// name
			$nodetext .= $name ;

			
			// occupancy	
			if ($occupation) {
				$nodetext .= '<br>';
				$nodetext .= $occupation;
			}
			
			
			// birth date			
			if ($birth_date) {
				$nodetext .= '<br>*';
				$nodetext .= $birth_date;
				if ($birth_place) {
					$nodetext .= " " . $birth_place;
				}
			}
			// death date
			if ($death_date) {
				$nodetext .= '<br>+';
				$nodetext .= $death_date;
				if ($death_place) {
					$nodetext .= " " . $death_place;
				}
			}
				
			$nodetext .= '</table></html>';
						
			$nodetext = str_replace("<","&lt;",$nodetext);
			$nodetext = str_replace(">","&gt;",$nodetext);

			$label_rows = count(explode("&lt;br&gt;", $nodetext)) + 1;
			// create node
			$buffer .= '<node id="' . $row->xref . '">' . "\n" .
					'<data key="d1"><![CDATA[http://my.site.com/'. $row->xref . ".html]]></data>\n" .
					'<data key="d2"><![CDATA[<html><body><p>' . $name . "</p></body></html>]]></data>\n" .
					'<data key="d3">' . '<y:GenericNode configuration="'
					. $node_style . '"> <y:Geometry height="'
					. (6 + 15 * $label_rows) .'" width="130" x="10" y="10"/> <y:Fill color="' 
					. $col . '" transparent="false"/> <y:BorderStyle color="'
					. $color_frame . '" type="line" width="1.0"/> <y:NodeLabel alignment="center" autoSizePolicy="content" hasBackgroundColor="false" hasLineColor="false" textColor="#000000" fontFamily="Dialog" fontSize="12" fontStyle="plain" visible="true" modelName="internal" modelPosition="c" width="129" height="19" x="1" y="1">';
					
			// no line break befor $nodetext allowed
			$buffer .= $nodetext . "\n" . 
						'</y:NodeLabel> </y:GenericNode> </data>' . "\n" .
						 "</node>\n";
			
			// write to file
			if (strlen ( $buffer ) > 65536) {
				fwrite ( $gedout, $buffer );
				$buffer = '';
			}
		}
		
		/*
		*  Create nodes for families
		*/
		// Get all families
		$rows = Database::prepare ( "SELECT f_id AS xref, f_gedcom AS gedcom" . " FROM `##families` WHERE f_file = :tree_id ORDER BY f_id" )->execute ( array (
				'tree_id' => $tree->getTreeId () 
		) )->fetchAll ();
		
		// loop over all families
		foreach ( $rows as $row ) {
			$record = Family::getInstance ( $row->xref, $tree);

			// marriage date
			if ($record->getMarriage()) {
				$date = "oo ";
				if ($include_dates_marriage == "full_date") {
					$marriage_date .= $record->getMarriageDate();
					if ($marriage_date instanceof Date) {
						$date .= strip_tags ($marriage_date->display());
					}
				} elseif ($include_dates_marriage== "year" && $record->getMarriageYear() > 0) {
					$date .= $record->getMarriageYear();
				}
			} else {
				$date = null;
			}

			
				
			
			$buffer .= '<node id="' . $row->xref . '">' . "\n";
			
			$buffer .= '<data key="d1"><![CDATA[http://my.site.com/'. $row->xref .'.html]]></data>';
			$buffer .= '<data key="d2"><![CDATA[   ]]></data>' ."\n";
			
			$buffer .= '<data key="d3"> <y:ShapeNode>'
					. '<y:Geometry height="1.0" width="'
					. $edge_line_width . '" x="28" y="28"/>' // '" x="28" y="28"/>'
					. '<y:Fill color="#000000" color2="#000000" transparent="false"/>'
					. '<y:BorderStyle hasColor="false" type="line" width="1.0"/>'
					. '<y:NodeLabel alignment="center" autoSizePolicy="content" backgroundColor="#ffffff" hasLineColor="false" textColor="#000000" fontFamily="Dialog" fontSize="12" fontStyle="plain" visible="true" modelName="internal" modelPosition="c" width="77" height="34" x="10" y="10">';

			// add date
			if ($date) $buffer .= $date;					
					
			$buffer .=	'</y:NodeLabel> <y:Shape type="rectangle"/>'
					. '</y:ShapeNode> </data>' . "\n"
					. "</node>\n";
			
			if (strlen ( $buffer ) > 65536) {
				fwrite ( $gedout, $buffer );
				$buffer = '';
			}
		}
				
		/*
		*  Create edges from families to individuals
		*/
		$no_edge = 0;
		// loop over families
		foreach ( $rows as $row ) {
			$record = Family::getInstance ( $row->xref, $tree);
				
			// add parents
			$parents = array($record->getHusband(), $record->getWife());
			
			foreach ( $parents as $parent ) {
				if ($parent) {
					$no_edge += 1;
					$buffer .= '<edge id="' .$no_edge . '" source="'
							. $parent->getXref() . '" target="' . $row->xref . '">' . "\n";
				
					$buffer .= '<data key="d6"> <y:PolyLineEdge> <y:Path sx="0.0" sy="17.5" tx="0.0" ty="-10"/> <y:LineStyle color="#000000" type="line" width="'
							. $edge_line_width . '"/> <y:Arrows source="none" target="none"/> <y:BendStyle smoothed="false"/> </y:PolyLineEdge> </data>' . "\n" . '</edge>' . "\n";
				}
			}

			// now add edges for children	
			$children = $record->getChildren();
				
			foreach ($children as $child) {
				$no_edge += 1;
				$buffer .= '<edge id="' .$no_edge . '" source="'
						. $row->xref . '" target="' . $child->getXref() . '">' . "\n";
							
					$buffer .= '<data key="d6"> <y:PolyLineEdge> <y:Path sx="0.0" sy="17.5" tx="0.0" ty="-10"/> <y:LineStyle color="#000000" type="line" width="'
							. $edge_line_width . '"/> <y:Arrows source="none" target="none"/> <y:BendStyle smoothed="false"/> </y:PolyLineEdge> </data>' . "\n" . '</edge>' . "\n";
						
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