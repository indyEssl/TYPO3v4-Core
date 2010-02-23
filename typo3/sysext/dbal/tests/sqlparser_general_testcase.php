<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Xavier Perseguers <typo3@perseguers.ch>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/


require_once('BaseTestCase.php');

/**
 * Testcase for class ux_t3lib_sqlparser
 * 
 * $Id: sqlparser_general_testcase.php 30009 2010-02-14 17:41:47Z xperseguers $
 *
 * @author Xavier Perseguers <typo3@perseguers.ch>
 *
 * @package TYPO3
 * @subpackage dbal
 */
class sqlparser_general_testcase extends BaseTestCase {

	/**
	 * @var ux_t3lib_sqlparser (extended to make protected methods public)
	 */
	protected $fixture;

	/**
	 * Prepares the environment before running a test.
	 */
	public function setUp() {
		$className = self::buildAccessibleProxy('ux_t3lib_sqlparser');
		$this->fixture = new $className;
	}

	/**
	 * Cleans up the environment after running a test.
	 */
	public function tearDown() {
		unset($this->fixture);
	}

	/**
	 * Cleans a SQL query.
	 *  
	 * @param mixed $sql
	 * @return mixed (string or array)
	 */
	private function cleanSql($sql) {
		if (!is_string($sql)) {
			return $sql;
		}

		$sql = str_replace("\n", ' ', $sql);
		$sql = preg_replace('/\s+/', ' ', $sql);
		return trim($sql);
	}

	/**
	 * @test
	 */
	public function canExtractPartsOfAQuery() {
		$parseString = "SELECT   *\nFROM pages WHERE pid IN (1,2,3,4)";
		$regex = '^SELECT[[:space:]]+(.*)[[:space:]]+';
		$trimAll = TRUE;
		$fields = $this->fixture->_callRef('nextPart', $parseString, $regex, $trimAll);

		$this->assertEquals(
			'*',
			$fields
		);
		$this->assertEquals(
			'FROM pages WHERE pid IN (1,2,3,4)',
			$parseString
		);

		$regex = '^FROM ([^)]+) WHERE';
		$table = $this->fixture->_callRef('nextPart', $parseString, $regex);

		$this->assertEquals(
			'pages',
			$table
		);
		$this->assertEquals(
			'pages WHERE pid IN (1,2,3,4)',
			$parseString
		);
	}

	/**
	 * @test
	 */
	public function canGetIntegerValue() {
		$parseString = '1024';
		$value = $this->fixture->_callRef('getValue', $parseString);
		$expected = array(1024);

		$this->assertEquals($expected, $value);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=13104
	 */
	public function canGetStringValue() {
		$parseString = '"some owner\\\'s string"';
		$value = $this->fixture->_callRef('getValue', $parseString);
		$expected = array('some owner\'s string', '"');

		$this->assertEquals($expected, $value);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=13104
	 */
	public function canGetStringValueWithSingleQuote() {
		$parseString = "'some owner\'s string'";
		$value = $this->fixture->_callRef('getValue', $parseString);
		$expected = array('some owner\'s string', "'");

		$this->assertEquals($expected, $value);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=13104
	 */
	public function canGetStringValueWithDoubleQuote() {
		$parseString = '"the \"owner\" is here"';
		$value = $this->fixture->_callRef('getValue', $parseString);
		$expected = array('the "owner" is here', '"');

		$this->assertEquals($expected, $value);
	}

	/**
	 * @test
	 */
	public function canGetListOfValues() {
		$parseString = '( 1,   2, 3  ,4)';
		$operator = 'IN';
		$values = $this->fixture->_callRef('getValue', $parseString, $operator);
		$expected = array(
			array(1),
			array(2),
			array(3),
			array(4)
		);

		$this->assertEquals($expected, $values);
	}

	/**
	 * @test
	 */
	public function parseWhereClauseReturnsArray() {
		$parseString = 'uid IN (1,2) AND (starttime < ' . time() . ' OR cruser_id + 10 < 20)';
		$where = $this->fixture->parseWhereClause($parseString);

		$this->assertTrue(is_array($where), $where);
		$this->assertTrue(empty($parseString), 'parseString is not empty');
	}

	/**
	 * @test
	 */
	public function canSelectAllFieldsFromPages() {
		$sql = 'SELECT * FROM pages';
		$expected = $sql;
		$actual = $this->cleanSql($this->fixture->debug_testSQL($sql)); 

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @test
	 * http://bugs.typo3.org/view.php?id=13504
	 */
	public function canParseTruncateTable() {
		$sql = 'TRUNCATE TABLE be_users';
		$expected = $sql;
		$actual = $this->cleanSql($this->fixture->debug_testSQL($sql));

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=13412
	 */
	public function canParseAndCompileBetweenOperator() {
		$parseString = '((scheduled BETWEEN 1265068628 AND 1265068828 ) OR scheduled <= 1265068728) AND NOT exec_time AND NOT process_id AND page_id=1 AND parameters_hash = \'854e9a2a77\'';
		$where = $this->fixture->parseWhereClause($parseString);

		$this->assertTrue(is_array($where), $where);
		$this->assertTrue(empty($parseString), 'parseString is not empty');

		$whereClause = $this->cleanSql($this->fixture->compileWhereClause($where));
		$expected = '((scheduled BETWEEN 1265068628 AND 1265068828) OR scheduled <= 1265068728) AND NOT exec_time AND NOT process_id AND page_id = 1 AND parameters_hash = \'854e9a2a77\'';
		$this->assertEquals($expected, $whereClause);
	}

	/**
	 * @test
	 * http://bugs.typo3.org/view.php?id=13430
	 */
	public function canParseInsertWithoutSpaceAfterValues() {
		$parseString = "INSERT INTO static_country_zones VALUES('483', '0', 'NL', 'NLD', '528', 'DR', 'Drenthe', '');";
		$components = $this->fixture->_callRef('parseINSERT', $parseString);

		$this->assertTrue(is_array($components), $components);
		$insert = $this->cleanSql($this->fixture->_callRef('compileINSERT', $components));
		$expected = "INSERT INTO static_country_zones VALUES ('483', '0', 'NL', 'NLD', '528', 'DR', 'Drenthe', '')";
		$this->assertEquals($expected, $insert);
	}

	/**
	 * @test
	 * http://bugs.typo3.org/view.php?id=13430
	 */
	public function canParseInsertWithSpaceAfterValues() {
		$parseString = "INSERT INTO static_country_zones VALUES ('483', '0', 'NL', 'NLD', '528', 'DR', 'Drenthe', '');";
		$components = $this->fixture->_callRef('parseINSERT', $parseString);

		$this->assertTrue(is_array($components), $components);
		$insert = $this->cleanSql($this->fixture->_callRef('compileINSERT', $components));
		$expected = "INSERT INTO static_country_zones VALUES ('483', '0', 'NL', 'NLD', '528', 'DR', 'Drenthe', '')";
		$this->assertEquals($expected, $insert);
	}

	/**
	 * @test
	 */
	public function canParseInsertWithFields() {
		$parseString = 'INSERT INTO static_territories (uid, pid, tr_iso_nr, tr_parent_iso_nr, tr_name_en) ';
		$parseString .= "VALUES ('1', '0', '2', '0', 'Africa');";
		$components = $this->fixture->_callRef('parseINSERT', $parseString);

		$this->assertTrue(is_array($components), $components);
		$insert = $this->cleanSql($this->fixture->_callRef('compileINSERT', $components));
		$expected = 'INSERT INTO static_territories (uid, pid, tr_iso_nr, tr_parent_iso_nr, tr_name_en) ';
		$expected .= "VALUES ('1', '0', '2', '0', 'Africa')";
		$this->assertEquals($expected, $insert);
	}

	/**
	 * @test
	 * http://bugs.typo3.org/view.php?id=13209
	 */
	public function canParseExtendedInsert() {
		$parseString = "INSERT INTO static_territories VALUES ('1', '0', '2', '0', 'Africa'),('2', '0', '9', '0', 'Oceania')," .
			"('3', '0', '19', '0', 'Americas'),('4', '0', '142', '0', 'Asia');";
		$components = $this->fixture->_callRef('parseINSERT', $parseString);

		$this->assertTrue(is_array($components), $components);
		$insert = $this->cleanSql($this->fixture->_callRef('compileINSERT', $components));
		$expected = "INSERT INTO static_territories VALUES ('1', '0', '2', '0', 'Africa'), ('2', '0', '9', '0', 'Oceania'), " .
			"('3', '0', '19', '0', 'Americas'), ('4', '0', '142', '0', 'Asia')";
		$this->assertEquals($expected, $insert);
	}

	/**
	 * @test
	 * http://bugs.typo3.org/view.php?id=13209
	 */
	public function canParseExtendedInsertWithFields() {
		$parseString = 'INSERT INTO static_territories (uid, pid, tr_iso_nr, tr_parent_iso_nr, tr_name_en) ';
		$parseString .= "VALUES ('1', '0', '2', '0', 'Africa'),('2', '0', '9', '0', 'Oceania');";
		$components = $this->fixture->_callRef('parseINSERT', $parseString);

		$this->assertTrue(is_array($components), $components);
		$insert = $this->cleanSql($this->fixture->_callRef('compileINSERT', $components));
		$expected = 'INSERT INTO static_territories (uid, pid, tr_iso_nr, tr_parent_iso_nr, tr_name_en) ';
		$expected .= "VALUES ('1', '0', '2', '0', 'Africa'), ('2', '0', '9', '0', 'Oceania')";
		$this->assertEquals($expected, $insert);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=6196
	 */
	public function canParseIfNullOperator() {
		$parseString = 'IFNULL(tt_news_cat_mm.uid_foreign,0) IN (21,22)';
		$whereParts = $this->fixture->parseWhereClause($parseString);

		$this->assertTrue(is_array($whereParts), $whereParts);
		$this->assertTrue(empty($parseString), 'parseString is not empty');
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=6196
	 */
	public function canParseIfNullOperatorWithAdditionalClauses() {
		$parseString = '1=1 AND IFNULL(tt_news_cat_mm.uid_foreign,0) IN (21,22) AND tt_news.sys_language_uid IN (0,-1) ';
		$parseString .= 'AND tt_news.pid > 0 AND tt_news.pid IN (61) AND tt_news.deleted=0 AND tt_news.t3ver_state<=0 ';
		$parseString .= 'AND tt_news.hidden=0 AND tt_news.starttime<=1266065460 AND (tt_news.endtime=0 OR tt_news.endtime>1266065460) ';
		$parseString .= 'AND (tt_news.fe_group=\'\' OR tt_news.fe_group IS NULL OR tt_news.fe_group=\'0\' ';
		$parseString .= 'OR (tt_news.fe_group LIKE \'%,0,%\' OR tt_news.fe_group LIKE \'0,%\' OR tt_news.fe_group LIKE \'%,0\' ';
		$parseString .= 'OR tt_news.fe_group=\'0\') OR (tt_news.fe_group LIKE \'%,-1,%\' OR tt_news.fe_group LIKE \'-1,%\' ';
		$parseString .= 'OR tt_news.fe_group LIKE \'%,-1\' OR tt_news.fe_group=\'-1\'))';
		$whereParts = $this->fixture->parseWhereClause($parseString);

		$this->assertTrue(is_array($whereParts), $whereParts);
		$this->assertTrue(empty($parseString), 'parseString is not empty');
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=6196
	 */
	public function canCompileIfNullOperator() {
		$parseString = 'SELECT * FROM tx_irfaq_q_cat_mm WHERE IFNULL(tx_irfaq_q_cat_mm.uid_foreign,0) = 1';
		$components = $this->fixture->_callRef('parseSELECT', $parseString);

		$this->assertTrue(is_array($components), $components);
		$select = $this->cleanSql($this->fixture->_callRef('compileSELECT', $components));
		$expected = 'SELECT * FROM tx_irfaq_q_cat_mm WHERE IFNULL(tx_irfaq_q_cat_mm.uid_foreign, 0) = 1';
		$this->assertEquals($expected, $select);
	}

	///////////////////////////////////////
	// Tests concerning JOINs
	///////////////////////////////////////

	/**
	 * @test
	 */
	public function parseFromTablesWithInnerJoinReturnsArray() {
		$parseString = 'be_users INNER JOIN pages ON pages.cruser_id = be_users.uid';
		$tables = $this->fixture->parseFromTables($parseString);

		$this->assertTrue(is_array($tables), $tables);
		$this->assertTrue(empty($parseString), 'parseString is not empty');
	}

	/**
	 * @test
	 */
	public function parseFromTablesWithLeftOuterJoinReturnsArray() {
		$parseString = 'be_users LEFT OUTER JOIN pages ON be_users.uid = pages.cruser_id';
		$tables = $this->fixture->parseFromTables($parseString);

		$this->assertTrue(is_array($tables), $tables);
		$this->assertTrue(empty($parseString), 'parseString is not empty');
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=12596
	 */
	public function parseFromTablesWithRightOuterJoinReturnsArray() {
		$parseString = 'tx_powermail_fieldsets RIGHT JOIN tt_content ON tx_powermail_fieldsets.tt_content = tt_content.uid';
		$tables = $this->fixture->parseFromTables($parseString);

		$this->assertTrue(is_array($tables), $tables);
		$this->assertTrue(empty($parseString), 'parseString is not empty');
	}

	/**
	 * @test
	 */
	public function parseFromTablesWithMultipleJoinsReturnsArray() {
		$parseString = 'be_users LEFT OUTER JOIN pages ON be_users.uid = pages.cruser_id INNER JOIN cache_pages cp ON cp.page_id = pages.uid';
		$tables = $this->fixture->parseFromTables($parseString);

		$this->assertTrue(is_array($tables), $tables);
		$this->assertTrue(empty($parseString), 'parseString is not empty');
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=12596
	 */
	public function parseFromTablesWithMultipleJoinsAndParenthesesReturnsArray() {
		$parseString = 'tx_powermail_fieldsets RIGHT JOIN tt_content ON tx_powermail_fieldsets.tt_content = tt_content.uid LEFT JOIN tx_powermail_fields ON tx_powermail_fieldsets.uid = tx_powermail_fields.fieldset';
		$tables = $this->fixture->parseFromTables($parseString);

		$this->assertTrue(is_array($tables), $tables);
		$this->assertTrue(empty($parseString), 'parseString is not empty');
	}

	/**
	 * @test
	 */
	public function canUseInnerJoinInSelect() {
		$sql = 'SELECT pages.uid, be_users.username FROM be_users INNER JOIN pages ON pages.cruser_id = be_users.uid';
		$expected = 'SELECT pages.uid, be_users.username FROM be_users INNER JOIN pages ON pages.cruser_id=be_users.uid';
		$actual = $this->cleanSql($this->fixture->debug_testSQL($sql)); 

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @test
	 */
	public function canUseMultipleInnerJoinsInSelect() {
		$sql = 'SELECT * FROM tt_news_cat INNER JOIN tt_news_cat_mm ON tt_news_cat.uid = tt_news_cat_mm.uid_foreign INNER JOIN tt_news ON tt_news.uid = tt_news_cat_mm.uid_local';
		$expected = 'SELECT * FROM tt_news_cat INNER JOIN tt_news_cat_mm ON tt_news_cat.uid=tt_news_cat_mm.uid_foreign INNER JOIN tt_news ON tt_news.uid=tt_news_cat_mm.uid_local';
		$actual = $this->cleanSql($this->fixture->debug_testSQL($sql)); 

		$this->assertEquals($expected, $actual);
	}

	///////////////////////////////////////
	// Tests concerning DB management
	///////////////////////////////////////

	/** 
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=4466
	 */
	public function indexMayContainALengthRestrictionInCreateTable() {
		$parseString = '
			CREATE TABLE tx_realurl_uniqalias (
				uid int(11) NOT NULL auto_increment,
				tstamp int(11) DEFAULT \'0\' NOT NULL,
				tablename varchar(60) DEFAULT \'\' NOT NULL,
				field_alias varchar(255) DEFAULT \'\' NOT NULL,
				field_id varchar(60) DEFAULT \'\' NOT NULL,
				value_alias varchar(255) DEFAULT \'\' NOT NULL,
				value_id int(11) DEFAULT \'0\' NOT NULL,
				lang int(11) DEFAULT \'0\' NOT NULL,
				expire int(11) DEFAULT \'0\' NOT NULL,

				PRIMARY KEY (uid),
				KEY tablename (tablename),
				KEY bk_realurl01 (field_alias,field_id,value_id,lang,expire),
				KEY bk_realurl02 (tablename,field_alias,field_id,value_alias(220),expire)
			);
		';

		$createTables = $this->fixture->_callRef('parseCREATETABLE', $parseString);
		$this->assertTrue(is_array($createTables), $createTables);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=12829
	 */
	public function indexMayContainALengthRestrictionInAlterTable() {
		$parseString = 'ALTER TABLE tx_realurl_uniqalias ADD KEY bk_realurl02 (tablename,field_alias,field_id,value_alias(220),expire)';
		$alterTables = $this->fixture->_callRef('parseALTERTABLE', $parseString);
		$this->assertTrue(is_array($alterTables), $alterTables);
	}

	///////////////////////////////////////
	// Tests concerning subqueries
	///////////////////////////////////////

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=12758
	 */
	public function inWhereClauseSupportsSubquery() {
		$parseString = 'process_id IN (SELECT process_id FROM tx_crawler_process WHERE active=0 AND deleted=0)';
		$whereParts = $this->fixture->parseWhereClause($parseString);

		$this->assertTrue(is_array($whereParts), $whereParts);
		$this->assertTrue(empty($parseString), 'parseString is not empty');
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=12758
	 */
	public function inWhereClauseWithSubqueryIsProperlyCompiled() {
		$sql = 'SELECT * FROM tx_crawler_queue WHERE process_id IN (SELECT process_id FROM tx_crawler_process WHERE active=0 AND deleted=0)';
		$expected = 'SELECT * FROM tx_crawler_queue WHERE process_id IN (SELECT process_id FROM tx_crawler_process WHERE active = 0 AND deleted = 0)';
		$actual = $this->cleanSql($this->fixture->debug_testSQL($sql));

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=12758
	 */
	public function whereClauseSupportsExistsKeyword() {
		$parseString = 'EXISTS (SELECT * FROM tx_crawler_queue WHERE tx_crawler_queue.process_id = tx_crawler_process.process_id AND tx_crawler_queue.exec_time = 0)';
		$whereParts = $this->fixture->parseWhereClause($parseString);

		$this->assertTrue(is_array($whereParts), $whereParts);
		$this->assertTrue(empty($parseString), 'parseString is not empty');
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=12758
	 */
	public function existsClauseIsProperlyCompiled() {
		$sql = 'SELECT * FROM tx_crawler_process WHERE active = 0 AND NOT EXISTS (SELECT * FROM tx_crawler_queue WHERE tx_crawler_queue.process_id = tx_crawler_process.process_id AND tx_crawler_queue.exec_time = 0)';
		$expected = 'SELECT * FROM tx_crawler_process WHERE active = 0 AND NOT EXISTS (SELECT * FROM tx_crawler_queue WHERE tx_crawler_queue.process_id = tx_crawler_process.process_id AND tx_crawler_queue.exec_time = 0)';
		$actual = $this->cleanSql($this->fixture->debug_testSQL($sql));

		$this->assertEquals($expected, $actual);
	}

	///////////////////////////////////////
	// Tests concerning advanced operators
	///////////////////////////////////////

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=13135
	 */
	public function caseWithBooleanConditionIsSupportedInFields() {
		$parseString = 'CASE WHEN 1>0 THEN 2 ELSE 1 END AS foo, other_column';
		$fieldList = $this->fixture->parseFieldList($parseString);

		$this->assertTrue(is_array($fieldList), $fieldList);
		$this->assertTrue(empty($parseString), 'parseString is not empty');
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=13135
	 */
	public function caseWithBooleanConditionIsProperlyCompiled() {
		$sql = 'SELECT CASE WHEN 1>0 THEN 2 ELSE 1 END AS foo, other_column FROM mytable';
		$expected = 'SELECT CASE WHEN 1 > 0 THEN 2 ELSE 1 END AS foo, other_column FROM mytable';
		$actual = $this->cleanSql($this->fixture->debug_testSQL($sql));

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=13135
	 */
	public function caseWithMultipleWhenIsSupportedInFields() {
		$parseString = 'CASE column WHEN 1 THEN \'one\' WHEN 2 THEN \'two\' ELSE \'out of range\' END AS number';
		$fieldList = $this->fixture->parseFieldList($parseString);

		$this->assertTrue(is_array($fieldList), $fieldList);
		$this->assertTrue(empty($parseString), 'parseString is not empty');
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=13135
	 */
	public function caseWithMultipleWhenIsProperlyCompiled() {
		$sql = 'SELECT CASE column WHEN 1 THEN \'one\' WHEN 2 THEN \'two\' ELSE \'out of range\' END AS number FROM mytable';
		$expected = 'SELECT CASE column WHEN 1 THEN \'one\' WHEN 2 THEN \'two\' ELSE \'out of range\' END AS number FROM mytable';
		$actual = $this->cleanSql($this->fixture->debug_testSQL($sql));

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=13134
	 */
	public function locateIsSupported() {
		$sql = 'SELECT * FROM tx_templavoila_tmplobj WHERE LOCATE(\'(fce)\', datastructure)>0';
		$expected = 'SELECT * FROM tx_templavoila_tmplobj WHERE LOCATE(\'(fce)\', datastructure) > 0';
		$actual = $this->cleanSql($this->fixture->debug_testSQL($sql));

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=13134
	 */
	public function locateWithPositionIsSupported() {
		$sql = 'SELECT * FROM tx_templavoila_tmplobj WHERE LOCATE(\'(fce)\'  , datastructure  ,10)>0';
		$expected = 'SELECT * FROM tx_templavoila_tmplobj WHERE LOCATE(\'(fce)\', datastructure, 10) > 0';
		$actual = $this->cleanSql($this->fixture->debug_testSQL($sql));

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=13134
	 * @see http://bugs.typo3.org/view.php?id=13135
	 */
	public function locateWithinCaseIsSupported() {
		$sql = 'SELECT *, CASE WHEN LOCATE(\'(fce)\', datastructure)>0 THEN 2 ELSE 1 END AS scope FROM tx_templavoila_tmplobj';
		$expected = 'SELECT *, CASE WHEN LOCATE(\'(fce)\', datastructure) > 0 THEN 2 ELSE 1 END AS scope FROM tx_templavoila_tmplobj';
		$actual = $this->cleanSql($this->fixture->debug_testSQL($sql));

		$this->assertEquals($expected, $actual);
	}
}
?>