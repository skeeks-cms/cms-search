<?php

use yii\base\InvalidConfigException;
use yii\db\Migration;
use yii\db\Schema;

class m160415_093837_create_table__cms_search_phrase extends Migration
{
    public function safeUp()
    {
        $tableExist = $this->db->getTableSchema("{{%cms_search_phrase}}", true);
        if ($tableExist) {
            return true;
        }

        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=InnoDB';
        }

        $this->createTable("{{%cms_search_phrase}}", [
            'id' => $this->primaryKey(),

            'created_by' => $this->integer(),
            'updated_by' => $this->integer(),

            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),

            'phrase' => $this->string(255),

            'result_count' => $this->integer()->notNull()->defaultValue(0),
            'pages' => $this->integer()->notNull()->defaultValue(0),

            'ip' => $this->string(32),
            'session_id' => $this->string(32),
            'site_code' => "CHAR(15) NULL",

            'data_server' => $this->text(),
            'data_session' => $this->text(),
            'data_cookie' => $this->text(),
            'data_request' => $this->text(),

        ], $tableOptions);

        $this->createIndex('cms_search_phrase__updated_by', '{{%cms_search_phrase}}', 'updated_by');
        $this->createIndex('cms_search_phrase__created_by', '{{%cms_search_phrase}}', 'created_by');
        $this->createIndex('cms_search_phrase__created_at', '{{%cms_search_phrase}}', 'created_at');
        $this->createIndex('cms_search_phrase__updated_at', '{{%cms_search_phrase}}', 'updated_at');

        $this->createIndex('cms_search_phrase__phrase', '{{%cms_search_phrase}}', 'phrase');
        $this->createIndex('cms_search_phrase__result_count', '{{%cms_search_phrase}}', 'result_count');
        $this->createIndex('cms_search_phrase__pages', '{{%cms_search_phrase}}', 'pages');
        $this->createIndex('cms_search_phrase__ip', '{{%cms_search_phrase}}', 'ip');
        $this->createIndex('cms_search_phrase__session_id', '{{%cms_search_phrase}}', 'session_id');
        $this->createIndex('cms_search_phrase__site_code', '{{%cms_search_phrase}}', 'site_code');


        $this->addForeignKey(
            'cms_search_phrase_created_by', "{{%cms_search_phrase}}",
            'created_by', '{{%cms_user}}', 'id', 'SET NULL', 'SET NULL'
        );

        $this->addForeignKey(
            'cms_search_phrase_updated_by', "{{%cms_search_phrase}}",
            'updated_by', '{{%cms_user}}', 'id', 'SET NULL', 'SET NULL'
        );

        $this->addForeignKey(
            'cms_search_phrase_site_code_fk', "{{%cms_search_phrase}}",
            'site_code', '{{%cms_site}}', 'code', 'SET NULL', 'SET NULL'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey("cms_search_phrase_updated_by", "{{%cms_search_phrase}}");
        $this->dropForeignKey("cms_search_phrase_updated_by", "{{%cms_search_phrase}}");
        $this->dropForeignKey("cms_search_phrase_site_code_fk", "{{%cms_search_phrase}}");

        $this->dropTable("{{%cms_search_phrase}}");
    }
}