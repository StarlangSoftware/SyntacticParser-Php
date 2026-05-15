<?php

use olcaytaner\Corpus\Sentence;
use olcaytaner\ParseTree\TreeBank;
use olcaytaner\SyntacticParser\ProbabilisticContextFreeGrammar\ProbabilisticContextFreeGrammar;
use olcaytaner\SyntacticParser\ProbabilisticParser\ProbabilisticCYKParser;

class ProbabilisticContextFreeGrammarTest extends \PHPUnit\Framework\TestCase
{
    public function testPCFG()
    {
        $treeBank = new TreeBank("../trees");
        $pcfg = new ProbabilisticContextFreeGrammar($treeBank, 1);
        $pcfg2 = new ProbabilisticContextFreeGrammar("../rule-pcfg.txt", "../dictionary-pcfg.txt", 1);
        $this->assertEquals($pcfg->size(), $pcfg2->size());
        $treeBank2 = new TreeBank("../trees2");
        $pcfg3 = new ProbabilisticContextFreeGrammar($treeBank2, 1);
    }

    public function testParser()
    {
        $parser = new ProbabilisticCYKParser();
        $pcfg = new ProbabilisticContextFreeGrammar("../rule-pcfg.txt", "../dictionary-pcfg.txt", 1);
        $pcfg->convertToChomskyNormalForm();
        $sentence = new Sentence("yeni Büyük yasada karmaşık dil savaşı bulandırmıştır .");
        $parses1 = $parser->parse($pcfg, $sentence);
        $this->assertEquals(1, count($parses1));
    }


}