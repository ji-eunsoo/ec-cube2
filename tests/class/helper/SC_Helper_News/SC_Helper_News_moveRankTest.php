<?php

$HOME = realpath(__DIR__).'/../../../..';
require_once $HOME.'/tests/class/helper/SC_Helper_News/SC_Helper_News_TestBase.php';

class SC_Helper_News_moveRankTest extends SC_Helper_News_TestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->objNews = new SC_Helper_News_Ex();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // ///////////////////////////////////////

    public function testMoveRankTestニュースIDと移動先ランクを指定した場合対象のランクが移動する()
    {
        $objQuery = SC_Query_Ex::getSingletonInstance();
        $this->setUpNews();
        $news_id = 1001;
        $rank = 1;

        $this->expected = '4';

        $this->objNews->moveRank($news_id, $rank);

        $col = 'rank';
        $from = 'dtb_news';
        $where = 'news_id = ?';
        $whereVal = [$news_id];
        $res = $objQuery->get($col, $from, $where, $whereVal);
        $this->actual = $res;

        $this->verify();
    }
}
