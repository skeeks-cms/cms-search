<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 28.08.2015
 */

use yii\db\Migration;

class m200406_110601__alter_table__cms_search_phrase extends Migration
{
    public function safeUp()
    {
        $tableName = "cms_search_phrase";

        $this->addColumn($tableName, "cms_site_id", $this->integer());

        $result = \Yii::$app->db->createCommand(<<<SQL
            UPDATE 
                `cms_search_phrase` as spts 
                LEFT JOIN cms_site site on site.code = spts.site_code 
            SET 
                spts.`cms_site_id` = site.id
SQL
        )->execute();

        $this->dropForeignKey("cms_search_phrase_site_code_fk", $tableName);
        $this->dropColumn($tableName, "site_code");


        $this->addForeignKey(
            "{$tableName}__cms_site_id", $tableName,
            'cms_site_id', '{{%cms_site}}', 'id', 'CASCADE', 'CASCADE'
        );
    }

    public function safeDown()
    {
        echo "m200406_080601__alter_table__shop_order cannot be reverted.\n";
        return false;
    }
}