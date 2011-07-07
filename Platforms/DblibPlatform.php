<?php
/*
 *  $Id$
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Realestate\MssqlBundle\Platforms;

use Doctrine\DBAL\DBALException,
    Doctrine\DBAL\Schema\TableDiff;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * The DblibPlatform provides the behavior, features and SQL dialect of the
 * MsSQL database platform.
 *
 * @since 2.0
 * @author Scott Morken <scott.morken@pcmail.maricopa.edu>
 * @author Roman Borschel <roman@code-factory.org>
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class DblibPlatform extends AbstractPlatform
{
     /**
     * Adds an adapter-specific LIMIT clause to the SELECT statement.
     * [ borrowed from Zend Framework ]
     *
     * @param string $query
     * @param mixed $limit
     * @param mixed $offset
     * @link http://lists.bestpractical.com/pipermail/rt-devel/2005-June/007339.html
     * @return string
     * @override
     */
    public function writeLimitClause($query, $limit = false, $offset = false)
    {
        if ($limit > 0) {
            $count = intval($limit);

            $offset = intval($offset);
            if ($offset < 0) {
                throw DBALException::limitOffsetInvalid($offset);
            }

            $orderby = stristr($query, 'ORDER BY');
            if ($orderby !== false) {
                $sort = (stripos($orderby, 'desc') !== false) ? 'desc' : 'asc';
                $order = str_ireplace('ORDER BY', '', $orderby);
                $order = trim(preg_replace('/ASC|DESC/i', '', $order));
            }

            $query = preg_replace('/^SELECT\s/i', 'SELECT TOP ' . ($count+$offset) . ' ', $query);

            $query = 'SELECT * FROM (SELECT TOP ' . $count . ' * FROM (' . $query . ') AS inner_tbl';
            if ($orderby !== false) {
                $query .= ' ORDER BY ' . $order . ' ';
                $query .= (stripos($sort, 'asc') !== false) ? 'DESC' : 'ASC';
            }
            $query .= ') AS outer_tbl';
            if ($orderby !== false) {
                $query .= ' ORDER BY ' . $order . ' ' . $sort;
            }

            return $query;

        }

        return $query;
    }


    /**
     * Returns the regular expression operator.
     *
     * @return string
     * @override
     */
    public function getRegexpExpression()
    {
        return 'RLIKE';
    }

    /**
     * Return string to call a variable with the current timestamp inside an SQL statement
     * There are three special variables for current date and time:
     * - CURRENT_TIMESTAMP (date and time, TIMESTAMP type)
     * - CURRENT_DATE (date, DATE type)
     * - CURRENT_TIME (time, TIME type)
     *
     * @return string to call a variable with the current timestamp
     * @override
     */
    public function getNowExpression($type = 'timestamp')
    {
        switch ($type) {
            case 'time':
            case 'date':
            case 'timestamp':
            default:
                return 'GETDATE()';
        }
    }

    /**
     * return string to call a function to get a substring inside an SQL statement
     *
     * @return string to call a function to get a substring
     * @override
     */
    public function getSubstringExpression($value, $position, $length = null)
    {
        if ( ! is_null($length)) {
            return 'SUBSTRING(' . $value . ', ' . $position . ', ' . $length . ')';
        }
        return 'SUBSTRING(' . $value . ', ' . $position . ', LEN(' . $value . ') - ' . $position . ' + 1)';
    }

    /**
     * Returns string to concatenate two or more string parameters
     *
     * @param string $arg1
     * @param string $arg2
     * @param string $values...
     * @return string to concatenate two strings
     * @override
     */
    public function getConcatExpression()
    {
        $args = func_get_args();
        return '(' . implode(' + ', $args) . ')';
    }

    /**
     * Returns global unique identifier
     *
     * @return string to get global unique identifier
     * @override
     */
    public function getGuidExpression()
    {
        return 'NEWID()';
    }

    /**
     * Whether the platform prefers identity columns for ID generation.
     * MsSql prefers "autoincrement" identity columns since sequences can only
     * be emulated with a table.
     *
     * @return boolean
     * @override
     */
    public function prefersIdentityColumns()
    {
        return true;
    }

    /**
     * Whether the platform supports identity columns.
     * MsSql supports this through AUTO_INCREMENT columns.
     *
     * @return boolean
     * @override
     */
    public function supportsIdentityColumns()
    {
        return true;
    }

    /**
     * Whether the platform supports savepoints. MsSql does not.
     *
     * @return boolean
     * @override
     */
    public function supportsSavepoints()
    {
        return false;
    }

    public function getShowDatabasesSQL()
    {
        return 'SHOW DATABASES';
    }

    public function getListTablesSQL()
    {
        return "SELECT name FROM sysobjects WHERE type = 'U' AND name <> 'dtproperties' ORDER BY name";
    }

    public function getListTableIndexesSQL($table, $currentDatabase = null)
    {
        $table = \strtoupper($table);
        return sprintf("SELECT
                                IND.NAME [KEY_NAME], IND.INDEX_ID, IC.INDEX_COLUMN_ID, COL.NAME [COLUMN_NAME],
                                IND.*, IC.*, COL.*
                            FROM
                                SYS.INDEXES IND
                            INNER JOIN
                                SYS.INDEX_COLUMNS IC ON
                                  IND.OBJECT_ID = IC.OBJECT_ID AND IND.INDEX_ID = IC.INDEX_ID
                            INNER JOIN
                                SYS.COLUMNS COL ON
                                  IC.OBJECT_ID = COL.OBJECT_ID AND IC.COLUMN_ID = COL.COLUMN_ID
                            INNER JOIN
                                SYS.TABLES T ON
                                  IND.OBJECT_ID = T.OBJECT_ID
                            WHERE T.NAME = '%s'", $table);
    }

    public function getListTriggersSQL()
    {
        return "SELECT name FROM sysobjects WHERE xtype = 'TR'";
    }
    public function getListTableColumnsSQL($table, $database = null)
    {
        $table = strtoupper($table);
        return sprintf("SELECT * FROM INFORMATION_SCHEMA.Columns where TABLE_NAME = '%s'",$table);
    }
     public function getListTableForeignKeysSQL($table)
    {
        $table = strtoupper($table);
        return sprintf("SELECT C.TABLE_CATALOG [PKTABLE_QUALIFIER],
                                   C.TABLE_SCHEMA [PKTABLE_OWNER],
                                   C.TABLE_NAME [PKTABLE_NAME],
                                   KCU.COLUMN_NAME [PKCOLUMN_NAME],
                                   C2.TABLE_CATALOG [FKTABLE_QUALIFIER],
                                   C2.TABLE_SCHEMA [FKTABLE_OWNER],
                                   C2.TABLE_NAME [FKTABLE_NAME],
                                   KCU2.COLUMN_NAME [FKCOLUMN_NAME],
                                   RC.UPDATE_RULE,
                                   RC.DELETE_RULE,
                                   C.CONSTRAINT_NAME [FKCONSTRAINT_NAME],
                                   C2.CONSTRAINT_NAME [PKCONSTRAINT_NAME],
                                   CAST(7 AS SMALLINT) [DEFERRABILITY]
                            FROM   INFORMATION_SCHEMA.TABLE_CONSTRAINTS C
                                   INNER JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE KCU
                                     ON C.CONSTRAINT_SCHEMA = KCU.CONSTRAINT_SCHEMA
                                        AND C.CONSTRAINT_NAME = KCU.CONSTRAINT_NAME
                                   INNER JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS RC
                                     ON C.CONSTRAINT_SCHEMA = RC.CONSTRAINT_SCHEMA
                                        AND C.CONSTRAINT_NAME = RC.CONSTRAINT_NAME
                                   INNER JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS C2
                                     ON RC.UNIQUE_CONSTRAINT_SCHEMA = C2.CONSTRAINT_SCHEMA
                                        AND RC.UNIQUE_CONSTRAINT_NAME = C2.CONSTRAINT_NAME
                                   INNER JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE KCU2
                                     ON C2.CONSTRAINT_SCHEMA = KCU2.CONSTRAINT_SCHEMA
                                        AND C2.CONSTRAINT_NAME = KCU2.CONSTRAINT_NAME
                                        AND KCU.ORDINAL_POSITION = KCU2.ORDINAL_POSITION
                            WHERE  C.CONSTRAINT_TYPE = 'FOREIGN KEY' AND C.TABLE_NAME = '%s'", $table);
    }
    public function getListTableTriggersSQL($table)
    {
        $table = $this->_conn->quote($table,'text');
        return sprintf("SELECT name FROM sysobjects WHERE xtype = 'TR' AND object_name(parent_obj) = '%s'", $table);
    }

    public function getListViewsSQL($database)
    {
        return "SELECT name FROM sysobjects WHERE xtype = 'V'";
    }

    /**
     * drop an existing database
     *
     * @param string $name name of the database that should be dropped
     * @return string
     * @override
     */
    public function getDropDatabaseSQL($name)
    {
        return 'DROP DATABASE ' . $name;
    }

    public function getSetTransactionIsolationSQL($level)
    {
        return 'SET TRANSACTION ISOLATION LEVEL ' . $this->_getTransactionIsolationLevelSQL($level);
    }

    /**
     * @override
     */
    public function getIntegerTypeDeclarationSQL(array $field)
    {
        return 'INT' . $this->_getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * @override
     */
    public function getBigIntTypeDeclarationSQL(array $field)
    {
        return 'BIGINT' . $this->_getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * @override
     */
    public function getSmallIntTypeDeclarationSQL(array $field)
    {
        return 'SMALLINT' . $this->_getCommonIntegerTypeDeclarationSQL($field);
    }

    public function getVarcharTypeDeclarationSQL(array $field)
    {
        if ( ! isset($field['length'])) {
            if (array_key_exists('default', $field)) {
                $field['length'] = $this->getVarcharMaxLength();
            } else {
                $field['length'] = false;
            }
        }

        $length = ($field['length'] <= $this->getVarcharMaxLength()) ? $field['length'] : false;
        $fixed = (isset($field['fixed'])) ? $field['fixed'] : false;

        return $fixed ? ($length ? 'CHAR(' . $length . ')' : 'CHAR(255)')
                : ($length ? 'VARCHAR(' . $length . ')' : 'TEXT');
    }

    /** @override */
    public function getClobTypeDeclarationSQL(array $field)
    {
        return 'TEXT';
    }

    /**
     * @override
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef)
    {
        $autoinc = '';
        if ( ! empty($columnDef['autoincrement'])) {
            $autoinc = ' AUTO_INCREMENT';
        }
        $unsigned = (isset($columnDef['unsigned']) && $columnDef['unsigned']) ? ' UNSIGNED' : '';

        return $unsigned . $autoinc;
    }

    /**
     * @override
     */
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'CHAR(' . strlen('YYYY-MM-DD HH:MM:SS') . ')';
    }

    /**
     * @override
     */
    public function getDateTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'CHAR(' . strlen('YYYY-MM-DD') . ')';
    }

    /**
     * @override
     */
    public function getTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'CHAR(' . strlen('HH:MM:SS') . ')';
    }

    /**
     * @override
     */
    public function getBooleanTypeDeclarationSQL(array $field)
    {
        return 'BIT';
    }

    /**
     * Get the platform name for this instance
     *
     * @return string
     */
    public function getName()
    {
        return 'mssql';
    }

    /**
     * Adds an adapter-specific LIMIT clause to the SELECT statement.
     *
     * @param string $query
     * @param mixed $limit
     * @param mixed $offset
     * @link http://lists.bestpractical.com/pipermail/rt-devel/2005-June/007339.html
     * @return string
     */
    public function doModifyLimitQuery($query, $limit, $offset = null)
    {
        if ($limit > 0) {
            $count = intval($limit);
            $offset = intval($offset);

            if ($offset < 0) {
                throw new Doctrine_Connection_Exception("LIMIT argument offset=$offset is not valid");
            }

            $orderby = stristr($query, 'ORDER BY');

            if ($orderby !== false) {
                // Ticket #1835: Fix for ORDER BY alias
                // Ticket #2050: Fix for multiple ORDER BY clause
                $order = str_ireplace('ORDER BY', '', $orderby);
                $orders = explode(',', $order);

                for ($i = 0; $i < count($orders); $i++) {
                    $sorts[$i] = (stripos($orders[$i], ' DESC') !== false) ? 'DESC' : 'ASC';
                    $orders[$i] = trim(preg_replace('/\s+(ASC|DESC)$/i', '', $orders[$i]));

                    // find alias in query string
                    $helperString = stristr($query, $orders[$i]);

                    $fromClausePos = strpos($helperString, ' FROM ');
                    $fieldsString = substr($helperString, 0, $fromClausePos + 1);

                    $fieldArray = explode(',', $fieldsString);
                    $fieldArray = array_shift($fieldArray);
                    $aux2 = preg_split('/ as /i', $fieldArray);

                    $aliases[$i] = trim(end($aux2));
                }
            }

            // Ticket #1259: Fix for limit-subquery in MSSQL
            $selectRegExp = 'SELECT\s+';
            $selectReplace = 'SELECT ';

            if (preg_match('/^SELECT(\s+)DISTINCT/i', $query)) {
                $selectRegExp .= 'DISTINCT\s+';
                $selectReplace .= 'DISTINCT ';
            }

            $query = preg_replace('/^'.$selectRegExp.'/i', $selectReplace . 'TOP ' . ($count + $offset) . ' ', $query);
            $query = 'SELECT * FROM (SELECT TOP ' . $count . ' * FROM (' . $query . ') AS ' . 'inner_tbl';

            if ($orderby !== false) {
                $query .= ' ORDER BY ';

                for ($i = 0, $l = count($orders); $i < $l; $i++) {
                    if ($i > 0) { // not first order clause
                        $query .= ', ';
                    }

                    $query .= 'inner_tbl' . '.' . $aliases[$i] . ' ';
                    $query .= (stripos($sorts[$i], 'ASC') !== false) ? 'DESC' : 'ASC';
                }
            }

            $query .= ') AS ' . 'outer_tbl';

            if ($orderby !== false) {
                $query .= ' ORDER BY ';

                for ($i = 0, $l = count($orders); $i < $l; $i++) {
                    if ($i > 0) { // not first order clause
                        $query .= ', ';
                    }

                    $query .= 'outer_tbl' . '.' . $aliases[$i] . ' ' . $sorts[$i];
                }
            }
        }

        return $query;
    }

    /**
     * Get the insert sql for an empty insert statement
     *
     * @param string $tableName
     * @param string $identifierColumnName
     * @return string $sql
     */
    public function getEmptyIdentityInsertSQL($quotedTableName, $quotedIdentifierColumnName)
    {
        return 'INSERT INTO ' . $quotedTableName . ' DEFAULT VALUES';
    }

    /**
     * @inheritdoc
     */
    public function getTruncateTableSQL($tableName, $cascade = false)
    {
        return 'TRUNCATE TABLE '.$tableName;
    }

    /**
     * MsSql uses Table Hints for locking strategies instead of the ANSI SQL FOR UPDATE like hints.
     *
     * @return string
     */
    public function getForUpdateSQL()
    {
        return '';
    }

    /**
     * @license LGPL
     * @author Hibernate
     * @param  string $fromClause
     * @param  int $lockMode
     * @return string
     */
    public function appendLockHint($fromClause, $lockMode)
    {
        if ($lockMode == \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE) {
            return $fromClause . " WITH (UPDLOCK, ROWLOCK)";
        } else if ( $lockMode == \Doctrine\DBAL\LockMode::PESSIMISTIC_READ ) {
            return $fromClause . " WITH (HOLDLOCK, ROWLOCK)";
        } else {
            return $fromClause;
        }
    }

    /**
     * @override
     */
    protected function initializeDoctrineTypeMappings()
    {
        $this->doctrineTypeMapping = array(
            'bigint'            => 'bigint',
            'numeric'           => 'decimal',
            'bit'               => 'boolean',
            'smallint'          => 'smallint',
            'decimal'           => 'decimal',
            'smallmoney'        => 'integer',
            'int'               => 'integer',
            'tinyint'           => 'smallint',
            'money'             => 'integer',
            'float'             => 'float',
            'real'              => 'float',
            'double'            => 'float',
            'double precision'  => 'float',
            'date'              => 'date',
            'datetimeoffset'    => 'datetimetz',
            'datetime2'         => 'datetime',
            'datetime'          => 'datetime',
            'time'              => 'time',
            'char'              => 'string',
            'varchar'           => 'string',
            'text'              => 'text',
            'nchar'             => 'string',
            'nvarchar'          => 'string',
            'ntext'             => 'text',
            'binary'            => 'text',
            'varbinary'         => 'text',
            'image'             => 'text',
            'uniqueidentifier'  => 'uniqueidentifier',
        );
    }

    /**
     * @override
     */
    public function getDateTimeFormatString()
    {
        return 'Y-m-d H:i:s.u';
    }
}