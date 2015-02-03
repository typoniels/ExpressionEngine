<?php
/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2003 - 2014, EllisLab, Inc.
 * @license		http://ellislab.com/expressionengine/user-guide/license.html
 * @link		http://ellislab.com
 * @since		Version 3.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * ExpressionEngine Spam Module
 *
 * @package		ExpressionEngine
 * @subpackage	Modules
 * @category	Modules
 * @author		EllisLab Dev Team
 * @link		http://ellislab.com
 */

require_once PATH_MOD . 'spam/libraries/Document.php';

class Collection {

	public $documents = array();
	public $vocabulary = array();
	public $vectorizers = array();
	public $idf_lookup = array();
	public $corpus = "";
	public $limit = 1000;
	
	/**
	 * Register the rules we want to use for vectorizing
	 * 
	 * @access public
	 * @param array 	 $transformations  The transformations to use when 
	 * 					 				   calculating the vector
	 * @return void
	 */
	public function __construct($transformations = array())
	{
		foreach ($transformations as $transformation)
		{
			$this->register($transformation);
		}
	}

	/**
	 * Fit the vectorizer to our collection of sources, will return an array of 
	 * vectorized sources.
	 * 
	 * @param array $sources Array of source strings to fit
	 * @access public
	 * @return array
	 */
	public function fit_transform($sources)
	{
		$result = array();

		foreach ($sources as $source)
		{
			$result[] = $this->transform($source);
		}

		return $result;
	}

	/**
	 * Computes a vector of feature values suitable for using with Naive Bayes
	 * 
	 * @param string $source The string to vectorize
	 * @access public
	 * @return array An array of floats
	 */
	public function transform($source)
	{
		$vector = array();

		if ( ! empty($this->vectorizers))
		{
			foreach ($this->vectorizers as $transform)
			{
				$vectorized = $transform->vectorize($source);

				if ( ! is_array($vectorized))
				{
					$vectorized = array($vectorized);
				}

				$vector = array_merge($vector, $vectorized);
			}
		}

		return $vector;
	}

	/**
	 * Register a vectorizer rule
	 * 
	 * @param mixed $class 
	 * @access public
	 * @return void
	 */
	public function register($obj)
	{
		if ( ! $obj instanceOf Vectorizer)
		{
			throw new InvalidArgumentException($class.' must implement the Vectorizer interface.');
		}

		$this->vectorizers[] = $obj;
	}

}

// ------------------------------------------------------------------------

/**
 * ExpressionEngine Document Vectorizer Interface
 *
 * @package		ExpressionEngine
 * @subpackage	Core
 * @category	Core
 * @author		EllisLab Dev Team
 * @link		http://ellislab.com
 */
interface Vectorizer {

	/**
	 * Return an array of floats computed from the source string
	 *
	 * @param string $source
	 * @return float
	 */
	public function vectorize($source);

}

/* End of file Vectorize.php */
/* Location: ./system/expressionengine/modules/spam/libraries/Vectorize.php */
