<?php

/***************************************************************
 *  Copyright notice
 *  (c) 2019 Gregor Agnes (zwo3.de) <ga@zwo3.de>
 *  All rights reserved
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/


namespace Zwo3\MaskKesearchIndexer;

use Doctrine\DBAL\FetchMode;
use mysql_xdevapi\DatabaseObject;
use TeaminmediasPluswerk\KeSearch\Lib\Db;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

class AdditionalContentFields
{

    /**
     * @var string
     */
    public $maskColumns = '';

    public function __construct()
    {
        $this->maskColumns = $this->getMaskFieldsFromTable();
    }

    public function modifyPageContentFields(&$fields, $pageIndexer)
    {
        // Add the field "subheader" from the tt_content table, which is normally not indexed, to the list of fields.
        if ($this->maskColumns) {
            $fields .= "," . $this->maskColumns;
        }
    }

    public function modifyContentFromContentElement(string &$bodytext, array $ttContentRow, $pageIndexer)
    {
       if ($this->maskColumns) {
           $columns = explode(',', $this->maskColumns);
           foreach ($columns as $column) {
               if (!is_numeric($ttContentRow[$column])) {
                   // add the content to bodytext
                   $bodytext .= strip_tags($ttContentRow[$column]);
               } elseif ($ttContentRow[$column] ) {
                   // index the dependent table

                   $bodytext .= 'hallohallo';
                   $maskColumnsOfDependentTable = explode(',', $this->getMaskFieldsFromTable($column));
                   if ($maskColumnsOfDependentTable) {
                       $bodytext .= 'yes';
                        $bodytext = $this->getContentFromMaskFields($ttContentRow['pid'], $column, $maskColumnsOfDependentTable);

                   }
               }
           }
       }
    }


    private function getContentFromMaskFields($pid, $table, $columns) {

        $queryBuilder = Db::getQueryBuilder($table);
        //$queryBuilder->getRestrictions()->removeAll();
        $pageQuery = $queryBuilder
            ->select(...$columns)
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq(
                    'pid', $queryBuilder->createNamedParameter($pid)
                )
            )
            ->execute();

        $bodytext = '';
        while ($row = $pageQuery->fetch()) {
            foreach ($row as $content) {
                $bodytext .= strip_tags($content);
            }
        }

        return $bodytext;
    }

    private function getMaskFieldsFromTable($table = 'tt_content')
    {
        $link = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable($table);

        $sql = "SELECT GROUP_CONCAT(COLUMN_NAME) as columns
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE table_name = '" . $table . "'
    AND table_schema = '" . $link->getDatabase() . "'
    AND column_name LIKE 'tx_mask_%'
    GROUP BY table_name
    ";

        $statement = $link->prepare($sql);
        $statement->execute();

        while ($row = $statement->fetch(FetchMode::ASSOCIATIVE)) {
            return $row['columns'];
        }
    }
}