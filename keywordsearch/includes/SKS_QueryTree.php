<?php

// constants indicating keyword types
define( 'ATW_INIT' , 0 ); // beginning of the query string
define( 'ATW_CAT'  , 1 ); // category     - [[Category:X]]
define( 'ATW_PAGE' , 2 ); // page         - [[X]]
define( 'ATW_PROP' , 3 ); // property     - [[X:Value]]
define( 'ATW_VALUE', 4 ); // value        - [[Property:X]]
define( 'ATW_COMP' , 5 ); // comparator   - [[Property:[<>!~]Value]]
define( 'ATW_WILD' , 6 ); // wildcard     - [[Property:*]]
define( 'ATW_NUM'  , 7 ); // number       - [[Property:<X]]  // not in use
define( 'ATW_OR'   , 8 ); // disjunction  - [[Property:X]] OR [[Property:Y]]
define( 'ATW_CNCPT', 9 ); // concept      - [[Concept:X]]

// a keyword of type <key> must be followed by one that has a type in <value>	
$sksgExpectTypes = array(
	ATW_INIT	=> array(ATW_CAT, ATW_CNCPT, ATW_PAGE, ATW_PROP),
	ATW_CAT		=> array(ATW_CAT, ATW_CNCPT, ATW_PROP),
	ATW_CNCPT	=> array(ATW_CAT, ATW_CNCPT, ATW_PROP),
	ATW_COMP 	=> array(ATW_VALUE), // array(ATW_VALUE, ATW_NUM)
	ATW_PROP 	=> array(ATW_PAGE, ATW_VALUE, ATW_WILD, ATW_COMP, ATW_PROP), // also removed ATW_NUM here
	ATW_OR 		=> array(ATW_PROP),
	ATW_PAGE	=> array(ATW_PROP),
	ATW_WILD	=> array(ATW_PROP, ATW_OR),
	ATW_VALUE	=> array(ATW_PROP, ATW_OR),
	ATW_NUM		=> array(ATW_PROP, ATW_OR),
);	

class SKSKeyword {
	public function __construct($keyword, $type) {
		$this->keyword = $keyword;
		$this->type = $type;
	}
}

/**
 * Accesses and stores data about a string, such as whether it is 
 * a valid page, category, property, or property value
 */
class SKSKeywordData {
	public $types;
	
	public function __construct($keywords) {
		global $atwKwStore, $atwComparators;

		$this->kwString = implode(" ", $keywords);	
		$this->types = array();
		
		// the order of these statements influences the order of interpretations, to a degree.
		// for example, because ATW_CAT is first, <Category> <Property> will come before
		// <Page> <Property>
		
		if ( $this->kwString == "" ) {
			$this->types[] = ATW_INIT;
			return;
		}
		
		if ( $this->kwString == "*" ) {
			$this->types[] = ATW_WILD;
			return;
		}
		
		if ( $atwKwStore->isCategory($this->kwString) )
			$this->types[] = ATW_CAT;
			
		if ( $atwKwStore->isConcept($this->kwString) )
			$this->types[] = ATW_CNCPT;
			
		if ( $atwKwStore->isProperty($this->kwString) )
			$this->types[] = ATW_PROP;
		
		if ( $atwKwStore->isPage($this->kwString) ) 
			$this->types[] = ATW_PAGE;
						
		if ( in_array($this->kwString, $atwComparators) )
			$this->types[] = ATW_COMP;
			
		if ( $atwKwStore->isPropertyValue($this->kwString) )
			$this->types[] = ATW_VALUE;
			
		if ( is_numeric($this->kwString) )
			$this->types[] = ATW_NUM;
			
		if ( $this->kwString == "or" )
			$this->types[] = ATW_OR;
		
	}
	
	public function isValid() {
		return (count($this->types) > 0);
	}
}

/**
 * A 'node' in the query interpretation tree
 * takes an array $current of the current query component being worked on, which
 * is mostly useful for when we are recursively making an array out of the tree.
 * takes an array $remaining of the following components in the query string
 * and creates a child node for all valid splits of that component
 * i.e. for SKSQueryNode(array("course"), array("professor year foo bar")),
 * if both the pages (or category, property, or something else) "professor" and "professor year" exist
 * there will be child nodes created for "professor": "year foo bar" and "professor year" : "foo bar"
 */
class SKSQueryNode {
	public $current;
	public $children;	
	
	function __construct($current, $remaining) {
		global $sksgExpectTypes;
					
		$this->current = new SKSKeywordData( $current );
		$this->children = array();
		
		// the possible types of the next keyword given the current keywords' valid types
		$nextExpect = array();				
		foreach ($sksgExpectTypes as $type => $validfollowers) {			
			if (in_array($type, $this->current->types)) {
				$nextExpect = array_merge($nextExpect, $validfollowers);
			}
		}			
		
		// populate array of valid children
		for ($i=1; $i <= count($remaining); $i++) {
			$nextCurrent = array_slice($remaining, 0, $i);
			$nextRemaining = array_slice($remaining, $i);
			
			$child = new SKSQueryNode( $nextCurrent, $nextRemaining );
			
			if (array_intersect($child->current->types, $nextExpect) 
				&& ($child->children || !$nextRemaining) ) 
			{
				$this->children[] = $child;
			}
		}
	}
}

/**
 * takes the keyword string, creates an SKSQueryNode tree,
 * flattens the tree as valid possible interpretations
 * provides the ability to order interpretations by likelihood of
 * being correct.
 */
class SKSQueryTree {
	protected $root;
	
	public function __construct($string) {
		$this->queryString = trim($string);
		$keywords = preg_split( "/\s+/", $this->queryString );		
		$this->root = new SKSQueryNode( array(""), $keywords );
		
		$this->enumeratePaths();
		$this->rank();
	}
	
	/**
	 * returns the flattened trees as an array of paths (query interpretations)
	 */
	protected function enumeratePaths() {
		$this->paths = $this->paths( $this->root, array(ATW_INIT) );
	}
	
	/**
	 * recursively gets an array of possible interpretations 
	 * for a node and its descendants. respects $sksgExpectTypes
	 */
	protected function paths(&$node, $expectTypes) {
		global $sksgExpectTypes;
		
		$ret = array();	
		if (empty($node->children)) { 		// base case
			foreach ($node->current->types as $t) {
				$ret[] = array(new SKSKeyword($node->current->kwString, $t));
			}
			return $ret;
		}
		
		foreach ($node->current->types as &$type) {
			foreach ($node->children as &$child) {
				$a = array_intersect($child->current->types, $sksgExpectTypes[$type]);
				
				if ($a) {
					foreach ($this->paths($child, $a) as $intr) {
						if (in_array($intr[0]->type, $sksgExpectTypes[$type])) {
							$ret[] = array_merge(
								array(new SKSKeyword($node->current->kwString, $type)),
								$intr
							);
						}
					}				
				}
			}			
		}
		
		return $ret;
		
	}
	
	/**
	 * Gets a score for each $this->paths and sorts them by score.
	 */
	protected function rank() {
		$scored = array();
		foreach ($this->paths as $path) {
			$scored[] = array($path, $this->score($path));
		}
		
		usort(
			$scored, 
			create_function( 
				'$a, $b',
				'if ($a[1] == $b[1]) { return 0; } else { return $a[1] > $b[1] ? -1:1; }'
			)
		);
		
		$this->paths = array_map(
			create_function('$p', 'return $p[0];'),
			$scored
		);
	}
	
	/**
	 * returns an estimate of the likelihood that $path is a useful query interpretation
	 */
	protected function score(&$path) {
		global $atwCatStore;
		
		$score = 0.0;
		
		// first, if there are multiple selected categories, get the concordance.
		// the fact that we don't do anything similar in the case that a page, not a category,
		// is the selected item gives a desirable bias to interpretations that have categories
		
		$cats = array();
		foreach ($path as $kwObj) {
			if ($kwObj->type == ATW_CAT) {
				$cats[] = $kwObj->keyword;
			}
		}
				
		if (count($cats) > 1) {
			$score += $atwCatStore->overlap($cats);
		}
		
		// add the average concordance with the first category 
		// of all of the properties in the query interpretation
		if (!empty($cats) && $firstCat = $cats[0]) {		// for simplicity we only test overlap with first category
			$total = $n = (float)0;
			
			for ($i=0; $i<count($path); $i++) {
				if ($path[$i]->type == ATW_PROP) {
					$n++;
					if (@$path[$i+1]->type == ATW_PAGE) {
						$total += $atwCatStore->propertyRating($firstCat, $path[$i]->keyword, 'rel');
					} else if (in_array(@$path[$i+1]->type, array(ATW_VALUE, ATW_COMP))) {
						$total += $atwCatStore->propertyRating($firstCat, $path[$i]->keyword, 'att');
					} else {
						$total += $atwCatStore->propertyRating($firstCat, $path[$i]->keyword, 'all');
					}
				}
			}
				
			$score += @pow($total/$n,2);
			
		} else { 	// a page, not a category, is selected
			$page = $path[0]->keyword;			
			//todo
		}
		
		return $score;
		
	}
}
