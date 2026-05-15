<?php

namespace olcaytaner\SyntacticParser\ProbabilisticContextFreeGrammar;

use olcaytaner\DataStructure\CounterHashMap;
use olcaytaner\ParseTree\ParseNode;
use olcaytaner\ParseTree\ParseTree;
use olcaytaner\ParseTree\Symbol;
use olcaytaner\ParseTree\TreeBank;
use olcaytaner\SyntacticParser\ContextFreeGrammar\ContextFreeGrammar;
use olcaytaner\SyntacticParser\ContextFreeGrammar\Rule;
use olcaytaner\SyntacticParser\ContextFreeGrammar\RuleType;

class ProbabilisticContextFreeGrammar extends ContextFreeGrammar
{

    /**
     * Another constructor for the ProbabilisticContextFreeGrammar class. Constructs the lexicon from the leaf nodes of
     * the trees in the given treebank. Extracts rules from the non-leaf nodes of the trees in the given treebank. Also
     * sets the minimum frequency parameter.
     * @param TreeBank $treeBank Treebank containing the constituency trees.
     * @param int $minCount Minimum frequency parameter.
     */
    protected function constructor1(TreeBank $treeBank, int $minCount): void
    {
        $this->constructDictionary($treeBank);
        for ($i = 0; $i < $treeBank->size(); $i++) {
            $parseTree = $treeBank->get($i);
            $this->updateExceptionalWordsInTree($parseTree, $minCount);
            $this->addRules($parseTree->getRoot());
        }
        $variables = $this->getLeftSide();
        foreach ($variables as $variable) {
            $candidates = $this->getRulesWithLeftSideX($variable);
            $total = 0;
            foreach ($candidates as $candidate) {
                if ($candidate instanceof ProbabilisticRule){
                    $total += $candidate->getCount();
                }
            }
            foreach ($candidates as $candidate) {
                if ($candidate instanceof ProbabilisticRule){
                    $candidate->normalizeProbability($total);
                }
            }
        }
        $this->updateTypes();
        $this->minCount = $minCount;
    }

    /**
     * Constructor for the ProbabilisticContextFreeGrammar class. Reads the rules from the rule file, lexicon rules from
     * the dictionary file and sets the minimum frequency parameter.
     * @param string $ruleFileName File name for the rule file.
     * @param string $dictionaryFileName File name for the lexicon file.
     * @param int $minCount Minimum frequency parameter.
     */
    public function constructor2(string $ruleFileName, string $dictionaryFileName, int $minCount): void
    {
        $fh = fopen($ruleFileName, 'r');
        while ($line = fgets($fh)) {
            $rule = new ProbabilisticRule(trim($line));
            $this->rules[] = $rule;
            $this->rulesRightSorted[] = $rule;
        }
        fclose($fh);
        usort($this->rules, [Rule::class, "ruleCompare"]);
        usort($this->rulesRightSorted, [Rule::class, "ruleRightSideCompare"]);
        $this->readDictionary($dictionaryFileName);
        $this->updateTypes();
        $this->minCount = $minCount;
    }

    public function __construct(string|TreeBank|null $item1 = null, int|string|null $item2 = null, int|null $item3 = null)
    {
        $this->dictionary = new CounterHashMap();
        $this->rules = [];
        $this->rulesRightSorted = [];
        if ($item3 === null) {
            $this->constructor1($item1, $item2);
        } else {
            $this->constructor2($item1, $item2, $item3);
        }
    }

    /**
     * Converts a parse node in a tree to a rule. The symbol in the parse node will be the symbol on the leaf side of the
     * rule, the symbols in the child nodes will be the symbols on the right hand side of the rule.
     * @param ParseNode $parseNode Parse node for which a rule will be created.
     * @param bool $trim If true, the tags will be trimmed. If the symbol's data contains '-' or '=', this method trims all
     *             characters after those characters.
     * @return ProbabilisticRule|null A new rule constructed from a parse node and its children.
     */
    public static function toRule(ParseNode $parseNode, bool $trim): ?ProbabilisticRule
    {
        $right = [];
        if ($trim) {
            $left = $parseNode->getData()->trimSymbol();
        } else {
            $left = $parseNode->getData();
        }
        for ($i = 0; $i < $parseNode->numberOfChildren(); $i++) {
            $childNode = $parseNode->getChild($i);
            if ($childNode->getData() !== null) {
                if ($childNode->getData()->isTerminal()) {
                    $right[] = $childNode->getData();
                } else {
                    $right[] = $childNode->getData()->trimSymbol();
                }
            } else {
                return null;
            }
        }
        return new ProbabilisticRule($left, $right);
    }

    /**
     * Recursive method to generate all rules from a subtree rooted at the given node.
     * @param ParseNode $parseNode Root node of the subtree.
     */
    private function addRules(ParseNode $parseNode): void
    {
        $newRule = ProbabilisticContextFreeGrammar::toRule($parseNode, true);
        if ($newRule !== null) {
            $existedRule = $this->searchRule($newRule);
            if ($existedRule == null) {
                $this->addRule($newRule);
                $newRule->increment();
            } else {
                $existedRule->increment();
            }
        }
        for ($i = 0; $i < $parseNode->numberOfChildren(); $i++) {
            $childNode = $parseNode->getChild($i);
            if ($childNode->numberOfChildren() > 0) {
                $this->addRules($childNode);
            }
        }
    }

    /**
     * Calculates the probability of a parse node.
     * @param ParseNode $parseNode Parse node for which probability is calculated.
     * @return float Probability of a parse node.
     */
    private function probabilityOfParseNode(ParseNode $parseNode): float{
        $sum = 0.0;
        if ($parseNode->numberOfChildren() > 0) {
            $rule = $this->toRule($parseNode, true);
            $existedRule = $this->searchRule($rule);
            $sum += log($existedRule->getProbability());
            if ($existedRule->getType() != RuleType::TERMINAL){
                for ($i = 0; $i < $parseNode->numberOfChildren(); $i++) {
                    $childNode = $parseNode->getChild($i);
                    $sum += $this->probabilityOfParseNode($childNode);
                }
            }
        }
        return $sum;
    }

    /**
     * Calculates the probability of a parse tree.
     * @param ParseTree $parseTree Parse tree for which probability is calculated.
     * @return float Probability of the parse tree.
     */
    public function probability(ParseTree $parseTree): float{
        return $this->probabilityOfParseNode($parseTree->getRoot());
    }

    /**
     * In conversion to Chomsky Normal Form, rules like X -> Y are removed and new rules for every rule as Y -> beta are
     * replaced with X -> beta. The method first identifies all X -> Y rules. For every such rule, all rules Y -> beta
     * are identified. For every such rule, the method adds a new rule X -> beta. Every Y -> beta rule is then deleted.
     * The method also calculates the probability of the new rules based on the previous rules.
     */
    private function removeSingleNonTerminalFromRightHandSide(): void
    {
        $nonTerminalList = [];
        $removeCandidate = $this->getSingleNonTerminalCandidateToRemove($nonTerminalList);
        while ($removeCandidate !== null) {
            $ruleList = $this->getRulesWithRightSideX($removeCandidate);
            foreach ($ruleList as $rule) {
                if ($rule instanceof ProbabilisticRule) {
                    $candidateList = $this->getRulesWithLeftSideX($removeCandidate);
                    foreach ($candidateList as $candidate) {
                        if ($candidate instanceof ProbabilisticRule) {
                            $this->addRule(new ProbabilisticRule($rule->getLeftHandSide(), [...$candidate->getRightHandSide()], $candidate->getType(), $rule->getProbability() * $candidate->getProbability()));
                        }
                    }
                    $this->removeRule($rule);
                }
            }
            $nonTerminalList[] = $removeCandidate;
            $removeCandidate = $this->getSingleNonTerminalCandidateToRemove($nonTerminalList);
        }
    }

    /**
     * In conversion to Chomsky Normal Form, rules like A -> BC... are replaced with A -> X1... and X1 -> BC. This
     * method determines such rules and for every such rule, it adds new rule X1->BC and updates rule A->BC to A->X1.
     * The method sets the probability of the rules X1->BC to 1, and calculates the probability of the rules A -> X1...
     */
    private function updateMultipleNonTerminalFromRightHandSide(): void
    {
        $newVariableCount = 0;
        $updateCandidate = $this->getMultipleNonTerminalCandidateToUpdate();
        while ($updateCandidate !== null) {
            $newSymbol = new Symbol("X" . $newVariableCount);
            $rightA = $updateCandidate->getRightHandSideAt(0);
            $rightB = $updateCandidate->getRightHandSideAt(1);
            $this->updateAllMultipleNonTerminalWithNewRule($rightA, $rightB, $newSymbol);
            $this->addRule(new ProbabilisticRule($newSymbol, [$rightA, $rightB], RuleType::TWO_NON_TERMINAL, 1.0));
            $updateCandidate = $this->getMultipleNonTerminalCandidateToUpdate();
            $newVariableCount++;
        }
    }

    /**
     * The method converts the grammar into Chomsky normal form. First, rules like X -&gt;  Y are removed and new rules for
     * every rule as Y -&gt;  beta are replaced with X -&gt;  beta. Second, rules like A -&gt;  BC... are replaced with A -&gt;  X1...
     * and X1 -&gt;  BC.
     */
    public function convertToChomskyNormalForm(): void
    {
        $this->removeSingleNonTerminalFromRightHandSide();
        $this->updateMultipleNonTerminalFromRightHandSide();
        usort($this->rules, [Rule::class, "ruleCompare"]);
        usort($this->rulesRightSorted, [Rule::class, "ruleRightSideCompare"]);
    }

}