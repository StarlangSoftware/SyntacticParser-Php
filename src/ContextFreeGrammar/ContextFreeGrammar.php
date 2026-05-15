<?php

namespace olcaytaner\SyntacticParser\ContextFreeGrammar;

use olcaytaner\Corpus\Sentence;
use olcaytaner\Dictionary\Dictionary\Word;
use olcaytaner\DataStructure\CounterHashMap;
use olcaytaner\ParseTree\NodeCollector;
use olcaytaner\ParseTree\NodeCondition\IsLeaf;
use olcaytaner\ParseTree\ParseNode;
use olcaytaner\ParseTree\ParseTree;
use olcaytaner\ParseTree\Symbol;
use olcaytaner\ParseTree\TreeBank;

class ContextFreeGrammar
{
    protected CounterHashMap $dictionary;
    protected array $rules;
    protected array $rulesRightSorted;
    protected int $minCount = 1;

    /**
     * Another constructor for the ContextFreeGrammar class. Constructs the lexicon from the leaf nodes of the trees
     * in the given treebank. Extracts rules from the non-leaf nodes of the trees in the given treebank. Also sets the
     * minimum frequency parameter.
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
        $this->updateTypes();
        $this->minCount = $minCount;
    }

    /**
     * Constructor for the ContextFreeGrammar class. Reads the rules from the rule file, lexicon rules from the
     * dictionary file and sets the minimum frequency parameter.
     * @param string $ruleFileName File name for the rule file.
     * @param string $dictionaryFileName File name for the lexicon file.
     * @param int $minCount Minimum frequency parameter.
     */
    public function constructor2(string $ruleFileName, string $dictionaryFileName, int $minCount): void
    {
        $fh = fopen($ruleFileName, 'r');
        while ($line = fgets($fh)) {
            $rule = new Rule(trim($line));
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

    /**
     * Reads the lexicon for the grammar. Each line consists of two items, the terminal symbol and the frequency of
     * that symbol. The method fills the dictionary counter hash map according to this data.
     * @param string $dictionaryFileName File name of the lexicon.
     */
    protected function readDictionary(string $dictionaryFileName): void
    {
        $fh = fopen($dictionaryFileName, 'r');
        while ($line = fgets($fh)) {
            $items = explode(" ", trim($line));
            $this->dictionary->putNTimes($items[0], (int)$items[1]);
        }
        fclose($fh);
    }

    /**
     * Constructs the lexicon from the given treebank. Reads each tree and for each leaf node in each tree puts the
     * symbol in the dictionary.
     * @param TreeBank $treeBank Treebank containing the constituency trees.
     */
    protected function constructDictionary(TreeBank $treeBank): void
    {
        for ($i = 0; $i < $treeBank->size(); $i++) {
            $parseTree = $treeBank->get($i);
            $nodeCollector = new NodeCollector($parseTree->getRoot(), new IsLeaf());
            $leafList = $nodeCollector->collect();
            foreach ($leafList as $parseNode) {
                if ($parseNode instanceof ParseNode) {
                    $this->dictionary->put($parseNode->getData()->getName());
                }
            }
        }
    }

    /**
     * Updates the types of the rules according to the number of symbols on the right hand side. Rule type is TERMINAL
     * if the rule is like X -&gt;  a, SINGLE_NON_TERMINAL if the rule is like X -&gt;  Y, TWO_NON_TERMINAL if the rule is like
     * X -&gt;  YZ, MULTIPLE_NON_TERMINAL if the rule is like X -&gt;  YZT...
     */
    protected function updateTypes(): void
    {
        $nonTerminals = [];
        foreach ($this->rules as $rule) {
            if ($rule instanceof Rule) {
                $nonTerminals[] = $rule->getLeftHandSide()->getName();
            }
        }
        foreach ($this->rules as $rule) {
            if ($rule instanceof Rule) {
                if ($rule->getRightHandSize() > 2) {
                    $rule->setType(RuleType::MULTIPLE_NON_TERMINAL);
                } else {
                    if ($rule->getRightHandSize() == 2) {
                        $rule->setType(RuleType::TWO_NON_TERMINAL);
                    } else {
                        if ($rule->getRightHandSideAt(0)->isTerminal() || Word::isPunctuationSymbol($rule->getRightHandSideAt(0)->getName()) || !in_array($rule->getRightHandSideAt(0)->getName(), $nonTerminals)) {
                            $rule->setType(RuleType::TERMINAL);
                        } else {
                            $rule->setType(RuleType::SINGLE_NON_TERMINAL);
                        }
                    }
                }
            }
        }
    }

    /**
     * Updates the exceptional symbols of the leaf nodes in the trees. Constituency trees consists of rare symbols and
     * numbers, which are usually useless in creating constituency grammars. This is due to the fact that, numbers may
     * not occur exactly the same both in the train and/or test set, although they have the same meaning in general.
     * Similarly, when a symbol occurs in the test set but not in the training set, there will not be any rule covering
     * that symbol and therefore no parse tree will be generated. For those reasons, the leaf nodes containing numerals
     * are converted to the same terminal symbol, i.e. _num_; the leaf nodes containing rare symbols are converted to
     * the same terminal symbol, i.e. _rare_.
     * @param ParseTree $parseTree Parse tree to be updated.
     * @param int $minCount Minimum frequency for the terminal symbols to be considered as rare.
     */
    protected function updateExceptionalWordsInTree(ParseTree $parseTree, int $minCount): void
    {
        $nodeCollector = new NodeCollector($parseTree->getRoot(), new IsLeaf());
        $leafList = $nodeCollector->collect();
        foreach ($leafList as $parseNode) {
            if ($parseNode instanceof ParseNode) {
                $data = $parseNode->getData()->getName();
                if (preg_match("/^(\+)?\d+$/", $data) || (preg_match("/^(\+)?(\d+)?\.\d*$/", $data) && $data != '.')) {
                    $parseNode->setData(new Symbol("_num_"));
                } else {
                    if ($this->dictionary->count($data) < $minCount) {
                        $parseNode->setData(new Symbol("_rare_"));
                    }
                }
            }
        }
    }

    /**
     * Updates the exceptional words in the sentences for which constituency parse trees will be generated. Constituency
     * trees consist of rare symbols and numbers, which are usually useless in creating constituency grammars. This is
     * due to the fact that, numbers may not occur exactly the same both in the train and/or test set, although they have
     * the same meaning in general. Similarly, when a symbol occurs in the test set but not in the training set, there
     * will not be any rule covering that symbol and therefore no parse tree will be generated. For those reasons, the
     * words containing numerals are converted to the same terminal symbol, i.e. _num_; thewords containing rare symbols
     * are converted to the same terminal symbol, i.e. _rare_.
     * @param Sentence $sentence Sentence to be updated.
     */
    public function updateExceptionalWordsInSentence(Sentence $sentence): void
    {
        for ($i = 0; $i < $sentence->wordCount(); $i++) {
            $word = $sentence->getWord($i);
            if (preg_match("/^(\+)?\d+$/", $word->getName()) || (preg_match("/^(\+)?(\d+)?\.\d*$/", $word->getName()) && $word->getName() != '.')) {
                $word->setName("_num_");
            } else {
                if ($this->dictionary->count($word->getName()) < $this->minCount) {
                    $word->setName("_rare_");
                }
            }
        }
    }

    /**
     * After constructing the constituency tree with a parser for a sentence, it contains exceptional words such as
     * rare words and numbers, which are represented as _rare_ and _num_ symbols in the tree. Those words should be
     * converted to their original forms. This method replaces the exceptional symbols to their original forms by
     * replacing _rare_ and _num_ symbols.
     * @param ParseTree $parseTree Parse tree to be updated.
     * @param Sentence $sentence Original sentence for which constituency tree is generated.
     */
    public function reinsertExceptionalWordsFromSentence(ParseTree $parseTree, Sentence $sentence): void
    {
        $nodeCollector = new NodeCollector($parseTree->getRoot(), new IsLeaf());
        $leafList = $nodeCollector->collect();
        for ($i = 0; $i < count($leafList); $i++) {
            $parseNode = $leafList[$i];
            if ($parseNode instanceof ParseNode) {
                $treeWord = $parseNode->getData()->getName();
                $sentenceWord = $sentence->getWord($i)->getName();
                if ($treeWord == "_rare_" || $treeWord == "_num_") {
                    $parseNode->setData(new Symbol($sentenceWord));
                }
            }
        }
    }

    /**
     * Converts a parse node in a tree to a rule. The symbol in the parse node will be the symbol on the leaf side of the
     * rule, the symbols in the child nodes will be the symbols on the right hand side of the rule.
     * @param ParseNode $parseNode Parse node for which a rule will be created.
     * @param bool $trim If true, the tags will be trimmed. If the symbol's data contains '-' or '=', this method trims all
     *             characters after those characters.
     * @return Rule|null A new rule constructed from a parse node and its children.
     */
    public static function toRule(ParseNode $parseNode, bool $trim): ?Rule
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
                if ($childNode->getData()->isTerminal() || !$trim) {
                    $right[] = $childNode->getData();
                } else {
                    $right[] = $childNode->getData()->trimSymbol();
                }
            } else {
                return null;
            }
        }
        return new Rule($left, $right);
    }

    /**
     * Recursive method to generate all rules from a subtree rooted at the given node.
     * @param ParseNode $parseNode Root node of the subtree.
     */
    private function addRules(ParseNode $parseNode): void
    {
        $newRule = ContextFreeGrammar::toRule($parseNode, true);
        if ($newRule !== null) {
            $this->addRule($newRule);
        }
        for ($i = 0; $i < $parseNode->numberOfChildren(); $i++) {
            $childNode = $parseNode->getChild($i);
            if ($childNode->numberOfChildren() > 0) {
                $this->addRules($childNode);
            }
        }
    }

    /**
     * Checks if a given rule exists in the rule list by performing a binary search on the rule array.
     * @param array $rules Rules array
     * @param Rule $rule Searched rule
     * @return int the index of the search rule, if it is contained in the rules array; otherwise, (-(insertion point) - 1). The
     * insertion point is defined as the point at which the word would be inserted into the rules array.
     */
    private function binarySearch(array $rules, Rule $rule, callable $callback): int
    {
        $lo = 0;
        $hi = count($rules) - 1;
        while ($lo <= $hi) {
            $mid = floor(($lo + $hi) / 2);
            if ($callback($rules[$mid], $rule) == 0) {
                return $mid;
            }
            if ($callback($rules[$mid], $rule) <= 0) {
                $lo = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }
        return -($lo + 1);
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
     * Inserts a new rule into the correct position in the sorted rules and rulesRightSorted array lists.
     * @param Rule $newRule Rule to be inserted into the sorted array lists.
     */
    public function addRule(Rule $newRule): void
    {
        $pos = $this->binarySearch($this->rules, $newRule, [Rule::class, "ruleCompare"]);
        if ($pos < 0) {
            array_splice($this->rules, -$pos - 1, 0, [$newRule]);
            $pos = $this->binarySearch($this->rulesRightSorted, $newRule, [Rule::class, "ruleRightSideCompare"]);
            if ($pos >= 0) {
                $this->rulesRightSorted[] = $newRule;
            } else {
                array_splice($this->rulesRightSorted, -$pos - 1, 0, [$newRule]);
            }
        }
    }

    /**
     * Removes a given rule from the sorted rules and rulesRightSorted array lists.
     * @param Rule $rule Rule to be removed from the sorted array lists.
     */
    public function removeRule(Rule $rule): void
    {
        $pos = $this->binarySearch($this->rules, $rule, [Rule::class, "ruleCompare"]);
        if ($pos >= 0) {
            array_splice($this->rules, $pos, 1);
            $pos = $this->binarySearch($this->rulesRightSorted, $rule, [Rule::class, "ruleRightSideCompare"]);
            $posUp = $pos;
            while ($posUp >= 0 && Rule::ruleRightSideCompare($this->rulesRightSorted[$posUp], $rule) == 0) {
                if (Rule::ruleCompare($this->rulesRightSorted[$posUp], $rule) == 0) {
                    array_splice($this->rulesRightSorted, $posUp, 1);
                    return;
                }
                $posUp--;
            }
            $posDown = $pos + 1;
            while ($posDown < count($this->rulesRightSorted) && Rule::ruleRightSideCompare($this->rulesRightSorted[$posDown], $rule) == 0) {
                if (Rule::ruleCompare($this->rulesRightSorted[$posDown], $rule) == 0) {
                    array_splice($this->rulesRightSorted, $posDown, 1);
                    return;
                }
                $posDown++;
            }
        }
    }

    /**
     * Returns rules formed as X -&gt;  ... Since there can be more than one rule, which have X on the left side, the method
     * first binary searches the rule to obtain the position of such a rule, then goes up and down to obtain others
     * having X on the left side.
     * @param Symbol $X Left side of the rule
     * @return array Rules of the form X -&gt;  ...
     */
    public function getRulesWithLeftSideX(Symbol $X): array
    {
        $result = [];
        $dummyRule = new Rule($X, $X);
        $middle = $this->binarySearch($this->rules, $dummyRule, [Rule::class, "ruleLeftSideCompare"]);
        if ($middle >= 0) {
            $middleUp = $middle;
            while ($middleUp >= 0 && $this->rules[$middleUp]->getLeftHandSide() == $X) {
                $result[] = $this->rules[$middleUp];
                $middleUp--;
            }
            $middleDown = $middle + 1;
            while ($middleDown < count($this->rules) && $this->rules[$middleDown]->getLeftHandSide() == $X) {
                $result[] = $this->rules[$middleDown];
                $middleDown++;
            }
        }
        return $result;
    }

    /**
     * Returns all symbols X from terminal rules such as X -&gt;  a.
     * @return array All symbols X from terminal rules such as X -&gt;  a.
     */
    public function partOfSpeechTags(): array
    {
        $result = [];
        foreach ($this->rules as $rule) {
            if ($rule instanceof Rule && $rule->getType() == RuleType::TERMINAL && !in_array($rule->getLeftHandSide(), $result)) {
                $result[] = $rule->getLeftHandSide();
            }
        }
        return $result;
    }

    /**
     * Returns all symbols X from all rules such as X -&gt;  ...
     * @return array All symbols X from all rules such as X -&gt;  ...
     */
    public function getLeftSide(): array
    {
        $result = [];
        foreach ($this->rules as $rule) {
            if ($rule instanceof Rule && !in_array($rule->getLeftHandSide(), $result)) {
                $result[] = $rule->getLeftHandSide();
            }
        }
        return $result;
    }

    /**
     * Returns all rules with the given terminal symbol on the right hand side, that is it returns all terminal rules
     * such as X -&gt;  s
     * @param Symbol $s Terminal symbol on the right hand side.
     * @return array All rules with the given terminal symbol on the right hand side
     */
    public function getTerminalRulesWithRightSideX(Symbol $s): array
    {
        $result = [];
        $dummyRule = new Rule($s, [$s]);
        $middle = $this->binarySearch($this->rulesRightSorted, $dummyRule, [Rule::class, "ruleRightSideCompare"]);
        if ($middle >= 0) {
            $middleUp = $middle;
            while ($middleUp >= 0 && $this->rulesRightSorted[$middleUp]->getRightHandSideAt(0) == $s) {
                if ($this->rulesRightSorted[$middleUp]->getType() == RuleType::TERMINAL) {
                    $result[] = $this->rulesRightSorted[$middleUp];
                }
                $middleUp--;
            }
            $middleDown = $middle + 1;
            while ($middleDown < count($this->rulesRightSorted) && $this->rulesRightSorted[$middleDown]->getRightHandSideAt(0) == $s) {
                if ($this->rulesRightSorted[$middleDown]->getType() == RuleType::TERMINAL) {
                    $result[] = $this->rulesRightSorted[$middleDown];
                }
                $middleDown++;
            }
        }
        return $result;
    }

    /**
     * Returns all rules with the given non-terminal symbol on the right hand side, that is it returns all non-terminal
     * rules such as X -&gt;  S
     * @param Symbol $s Non-terminal symbol on the right hand side.
     * @return array All rules with the given non-terminal symbol on the right hand side
     */
    public function getRulesWithRightSideX(Symbol $s): array
    {
        $result = [];
        $dummyRule = new Rule($s, [$s]);
        $middle = $this->binarySearch($this->rulesRightSorted, $dummyRule, [Rule::class, "ruleRightSideCompare"]);
        if ($middle >= 0) {
            $middleUp = $middle;
            while ($middleUp >= 0 && $this->rulesRightSorted[$middleUp]->getRightHandSideAt(0) == $s && $this->rulesRightSorted[$middleUp]->getRightHandSize() == 1) {
                $result[] = $this->rulesRightSorted[$middleUp];
                $middleUp--;
            }
            $middleDown = $middle + 1;
            while ($middleDown < count($this->rulesRightSorted) && $this->rulesRightSorted[$middleDown]->getRightHandSideAt(0) == $s && $this->rulesRightSorted[$middleDown]->getRightHandSize() == 1) {
                $result[] = $this->rulesRightSorted[$middleDown];
                $middleDown++;
            }
        }
        return $result;
    }

    /**
     * Returns all rules with the given two non-terminal symbols on the right hand side, that is it returns all
     * non-terminal rules such as X -&gt;  AB.
     * @param Symbol $A First non-terminal symbol on the right hand side.
     * @param Symbol $B Second non-terminal symbol on the right hand side.
     * @return array All rules with the given two non-terminal symbols on the right hand side
     */
    public function getRulesWithTwoNonTerminalsOnRightSide(Symbol $A, Symbol $B): array
    {
        $result = [];
        $dummyRule = new Rule($A, $A, $B);
        $middle = $this->binarySearch($this->rulesRightSorted, $dummyRule, [Rule::class, "ruleRightSideCompare"]);
        if ($middle >= 0) {
            $middleUp = $middle;
            while ($middleUp >= 0 && $this->rulesRightSorted[$middleUp]->getRightHandSideAt(0) == $A && $this->rulesRightSorted[$middleUp]->getRightHandSideAt(1) == $B && $this->rulesRightSorted[$middleUp]->getRightHandSize() == 2) {
                $result[] = $this->rulesRightSorted[$middleUp];
                $middleUp--;
            }
            $middleDown = $middle + 1;
            while ($middleDown < count($this->rulesRightSorted) && $this->rulesRightSorted[$middleDown]->getRightHandSideAt(0) == $A && $this->rulesRightSorted[$middleDown]->getRightHandSideAt(1) == $B && $this->rulesRightSorted[$middleDown]->getRightHandSize() == 2) {
                $result[] = $this->rulesRightSorted[$middleDown];
                $middleDown++;
            }
        }
        return $result;
    }

    /**
     * Returns the symbol on the right side of the first rule with one non-terminal symbol on the right hand side, that
     * is it returns S of the first rule such as X -&gt;  S. S should also not be in the given removed list.
     * @param array $removedList Discarded list for symbol S.
     * @return Symbol|null The symbol on the right side of the first rule with one non-terminal symbol on the right hand side. The
     * symbol to be returned should also not be in the given discarded list.
     */
    protected function getSingleNonTerminalCandidateToRemove(array $removedList): ?Symbol
    {
        $removeCandidate = null;
        foreach ($this->rules as $rule) {
            if ($rule instanceof Rule && $rule->getType() == RuleType::SINGLE_NON_TERMINAL && !$rule->leftRecursive() && !in_array($rule->getRightHandSideAt(0), $removedList)) {
                $removeCandidate = $rule->getRightHandSideAt(0);
                break;
            }
        }
        return $removeCandidate;
    }

    /**
     * Returns all rules with more than two non-terminal symbols on the right hand side, that is it returns all
     * non-terminal rules such as X -&gt;  ABC...
     * @return Rule|null All rules with more than two non-terminal symbols on the right hand side.
     */
    protected function getMultipleNonTerminalCandidateToUpdate(): ?Rule
    {
        $removeCandidate = null;
        foreach ($this->rules as $rule) {
            if ($rule instanceof Rule && $rule->getType() == RuleType::MULTIPLE_NON_TERMINAL) {
                $removeCandidate = $rule;
                break;
            }
        }
        return $removeCandidate;
    }

    /**
     * In conversion to Chomsky Normal Form, rules like X -&gt;  Y are removed and new rules for every rule as Y -&gt;  beta are
     * replaced with X -&gt;  beta. The method first identifies all X -&gt;  Y rules. For every such rule, all rules Y -&gt;  beta
     * are identified. For every such rule, the method adds a new rule X -&gt;  beta. Every Y -&gt;  beta rule is then deleted.
     */
    private function removeSingleNonTerminalFromRightHandSide(): void
    {
        $nonTerminalList = [];
        $removeCandidate = $this->getSingleNonTerminalCandidateToRemove($nonTerminalList);
        while ($removeCandidate !== null) {
            $ruleList = $this->getRulesWithRightSideX($removeCandidate);
            foreach ($ruleList as $rule) {
                if ($rule instanceof Rule) {
                    $candidateList = $this->getRulesWithLeftSideX($removeCandidate);
                    foreach ($candidateList as $candidate) {
                        if ($candidate instanceof Rule) {
                            $this->addRule(new Rule($rule->getLeftHandSide(), [...$candidate->getRightHandSide()], $candidate->getType()));
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
     * In conversion to Chomsky Normal Form, rules like A -&gt;  BC... are replaced with A -&gt;  X1... and X1 -&gt;  BC. This
     * method replaces B and C non-terminals on the right hand side with X1 for all rules in the grammar.
     * @param Symbol $first Non-terminal symbol B.
     * @param Symbol $second Non-terminal symbol C.
     * @param Symbol $with Non-terminal symbol X1.
     */
    protected function updateAllMultipleNonTerminalWithNewRule(Symbol $first, Symbol $second, Symbol $with): void
    {
        foreach ($this->rules as $rule) {
            if ($rule instanceof Rule && $rule->getType() == RuleType::MULTIPLE_NON_TERMINAL) {
                $rule->updateMultipleNonTerminal($first, $second, $with);
            }
        }
    }

    /**
     * In conversion to Chomsky Normal Form, rules like A -&gt;  BC... are replaced with A -&gt;  X1... and X1 -&gt;  BC. This
     * method determines such rules and for every such rule, it adds new rule X1-&gt; BC and updates rule A-&gt; BC to A-&gt; X1.
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
            $this->addRule(new Rule($newSymbol, [$rightA, $rightB], RuleType::TWO_NON_TERMINAL));
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

    /**
     * Searches a given rule in the grammar.
     * @param Rule $rule Rule to be searched.
     * @return Rule|null Rule if found, null otherwise.
     */
    public function searchRule(Rule $rule): ?Rule
    {
        $pos = $this->binarySearch($this->rules, $rule, [Rule::class, "ruleCompare"]);
        if ($pos >= 0) {
            return $this->rules[$pos];
        } else {
            return null;
        }
    }

    /**
     * Returns number of rules in the grammar.
     * @return int Number of rules in the Context Free Grammar.
     */
    public function size(): int{
        return count($this->rules);
    }

    public function __toString(): string
    {
        $result = "";
        foreach ($this->rules as $rule) {
            $result .= $rule->__toString() . "\n";
        }
        return $result;
    }
}