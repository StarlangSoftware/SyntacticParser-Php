<?php

namespace olcaytaner\SyntacticParser\ProbabilisticParser;

use olcaytaner\Corpus\Sentence;
use olcaytaner\Dictionary\Dictionary\Word;
use olcaytaner\ParseTree\ParseNode;
use olcaytaner\ParseTree\ParseTree;
use olcaytaner\ParseTree\Symbol;
use olcaytaner\SyntacticParser\ProbabilisticContextFreeGrammar\ProbabilisticContextFreeGrammar;
use olcaytaner\SyntacticParser\ProbabilisticContextFreeGrammar\ProbabilisticParseNode;
use olcaytaner\SyntacticParser\ProbabilisticContextFreeGrammar\ProbabilisticRule;
use olcaytaner\SyntacticParser\SyntacticParser\PartialParseList;

class ProbabilisticCYKParser implements ProbabilisticParser
{
    /**
     * Constructs an array of most probable parse trees for a given sentence according to the given grammar. CYK parser
     * is based on a dynamic programming algorithm.
     * @param ProbabilisticContextFreeGrammar $pCfg Probabilistic context free grammar used in parsing.
     * @param Sentence $sentence Sentence to be parsed.
     * @return array Array list of most probable parse trees for the given sentence.
     */
    public function parse(ProbabilisticContextFreeGrammar $pCfg, Sentence $sentence): array
    {
        $parseTrees = [];
        $backup = new Sentence();
        for ($i = 0; $i < $sentence->wordCount(); $i++) {
            $backup->addWord(new Word($sentence->getWord($i)->getName()));
        }
        $pCfg->updateExceptionalWordsInSentence($sentence);
        $table = [];
        for ($i = 0; $i < $sentence->wordCount(); $i++) {
            $subTable = [];
            for ($j = 0; $j < $sentence->wordCount(); $j++) {
                $subTable[] = new PartialParseList();
            }
            $table[] = $subTable;
        }
        for ($i = 0; $i < $sentence->wordCount(); $i++) {
            $candidates = $pCfg->getTerminalRulesWithRightSideX(new Symbol($sentence->getWord($i)->getName()));
            foreach ($candidates as $candidate) {
                if ($candidate instanceof ProbabilisticRule && $table[$i][$i] instanceof PartialParseList) {
                    $table[$i][$i]->addPartialParse(new ProbabilisticParseNode(new ParseNode(new Symbol($sentence->getWord($i)->getName())), $candidate->getLeftHandSide(), $candidate->getProbability()));
                }
            }
        }
        for ($j = 1; $j < $sentence->wordCount(); $j++) {
            for ($i = $j - 1; $i >= 0; $i--) {
                for ($k = $i; $k < $j; $k++) {
                    for ($x = 0; $x < $table[$i][$k]->size(); $x++) {
                        for ($y = 0; $y < $table[$k + 1][$j]->size(); $y++) {
                            $leftNode = $table[$i][$k]->getPartialParse($x);
                            $rightNode = $table[$k + 1][$j]->getPartialParse($y);
                            $candidates = $pCfg->getRulesWithTwoNonTerminalsOnRightSide($leftNode->getData(), $rightNode->getData());
                            foreach ($candidates as $candidate) {
                                $probability = log($candidate->getProbability()) + $leftNode->getLogProbability() + $rightNode->getLogProbability();
                                $table[$i][$j]->addPartialParse(new ProbabilisticParseNode($leftNode, $rightNode, $candidate->getLeftHandSide(), $probability));
                            }
                        }
                    }
                }
            }
        }
        $bestProbability = -PHP_FLOAT_MAX;
        for ($i = 0; $i < $table[0][$sentence->wordCount() - 1]->size(); $i++) {
            if ($table[0][$sentence->wordCount() - 1]->getPartialParse($i)->getData()->getName() == "S" && $table[0][$sentence->wordCount() - 1]->getPartialParse($i)->getLogProbability() > $bestProbability) {
                $bestProbability = $table[0][$sentence->wordCount() - 1]->getPartialParse($i)->getLogProbability();
            }
        }
        for ($i = 0; $i < $table[0][$sentence->wordCount() - 1]->size(); $i++) {
            if ($table[0][$sentence->wordCount() - 1]->getPartialParse($i)->getData()->getName() == "S" && $table[0][$sentence->wordCount() - 1]->getPartialParse($i)->getLogProbability() == $bestProbability) {
                $parseTree = new ParseTree($table[0][$sentence->wordCount() - 1]->getPartialParse($i));
                $parseTree->correctParents();
                $parseTree->removeXNodes();
                $parseTrees[] = $parseTree;
            }
        }
        foreach ($parseTrees as $parseTree) {
            $pCfg->reinsertExceptionalWordsFromSentence($parseTree, $backup);
        }
        return $parseTrees;
    }
}