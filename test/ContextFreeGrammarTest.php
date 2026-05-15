<?php

use olcaytaner\Corpus\Sentence;
use olcaytaner\ParseTree\TreeBank;
use olcaytaner\SyntacticParser\ContextFreeGrammar\ContextFreeGrammar;
use olcaytaner\SyntacticParser\SyntacticParser\CYKParser;
use PHPUnit\Framework\TestCase;

class ContextFreeGrammarTest extends TestCase
{
    public function testCFG()
    {
        $treeBank = new TreeBank("../trees");
        $cfg = new ContextFreeGrammar($treeBank, 1);
        $cfg2 = new ContextFreeGrammar("../rule-cfg.txt", "../dictionary-cfg.txt", 1);
        $this->assertEquals($cfg->size(), $cfg2->size());
        $treeBank2 = new TreeBank("../trees2");
        $cfg3 = new ContextFreeGrammar($treeBank2, 1);
    }

    public function testParser()
    {
        $parser = new CYKParser();
        $cfg = new ContextFreeGrammar("../rule-cfg.txt", "../dictionary-cfg.txt", 1);
        $cfg->convertToChomskyNormalForm();
        $sentence = new Sentence("yeni Büyük yasada karmaşık dil savaşı bulandırmıştır .");
        $parses1 = $parser->parse($cfg, $sentence);
        $this->assertEquals(181, count($parses1));
    }

}