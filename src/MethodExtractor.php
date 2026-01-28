<?php

declare(strict_types=1);

namespace Verteller;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Throwable;

final class MethodExtractor
{
    private const string COVERS_STORY_ATTRIBUTE = 'Verteller\\Attributes\\CoversStory';

    /**
     * Extract existing CoversStory methods from a test file.
     *
     * @param string $filePath Path to the test file
     * @return array<string, ExtractedMethod> Scenario name => extracted method
     */
    public function extract(string $filePath): array
    {
        $methods = [];

        if (!file_exists(filename: $filePath) || !is_readable(filename: $filePath)) {
            return $methods;
        }

        $fqcn = $this->findClassInFile(file: $filePath);
        if (!$fqcn) {
            return $methods;
        }

        // Try autoloading first
        if (!class_exists(class: $fqcn, autoload: true)) {
            // Verify file is a safe test class before requiring it
            if (!$this->isSafeTestFile(filePath: $filePath)) {
                return $methods;
            }
            try {
                require_once $filePath;
            } catch (Throwable) {
                // File has syntax errors or other issues
                return $methods;
            }
        }

        try {
            $ref = new ReflectionClass(objectOrClass: $fqcn);
        } catch (ReflectionException) {
            return $methods;
        }

        foreach ($ref->getMethods(filter: ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes() as $attr) {
                if ($attr->getName() === self::COVERS_STORY_ATTRIBUTE) {
                    $args = $attr->getArguments();
                    $scenario = $args['scenario'] ?? null;
                    if ($scenario) {
                        $methods[$scenario] = new ExtractedMethod(
                            method: $method->getName(),
                            providerSnapshot: $this->extractProviderTable(ref: $ref, method: $method),
                            hash: $args['hash'] ?? null,
                        );
                    }
                }
            }
        }

        return $methods;
    }

    /**
     * Extract the provider table data from a data provider method.
     */
    private function extractProviderTable(ReflectionClass $ref, ReflectionMethod $method): ?string
    {
        $attrs = $method->getAttributes(name: 'PHPUnit\\Framework\\Attributes\\DataProvider');
        if (!$attrs) {
            return null;
        }

        $providerName = $attrs[0]->getArguments()[0] ?? null;
        if (!$providerName || !$ref->hasMethod(name: $providerName)) {
            return null;
        }

        $provMethod = $ref->getMethod(name: $providerName);

        try {
            $rows = $provMethod->invoke(object: null);

            // Ensure we have an array before processing
            if (!is_array(value: $rows)) {
                return null;
            }

            // Unwrap rows to match story table format (each row is wrapped in an array for PHPUnit)
            // Filter out non-array rows to ensure data integrity
            $unwrapped = [];
            foreach ($rows as $key => $row) {
                if (!is_array(value: $row)) {
                    continue;
                }
                // Unwrap single-element arrays
                $unwrapped[$key] = count(value: $row) === 1 ? $row[0] : $row;
            }
            return serialize(value: $unwrapped);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Verify that a file is a safe test class file before requiring it.
     *
     * Checks that the file:
     * - Only contains safe constructs in global scope (declare, namespace, use, class definition)
     * - The class extends TestCase
     * - No executable code in global scope
     */
    private function isSafeTestFile(string $filePath): bool
    {
        $contents = file_get_contents(filename: $filePath);
        if ($contents === false) {
            return false;
        }

        $tokens = token_get_all(code: $contents);

        // Tokens that are safe in the global scope (before class definition)
        $safeGlobalTokens = [
            T_OPEN_TAG,
            T_WHITESPACE,
            T_COMMENT,
            T_DOC_COMMENT,
            T_DECLARE,
            T_STRING,
            T_LNUMBER,
            T_CONSTANT_ENCAPSED_STRING,
            T_NAMESPACE,
            T_NAME_QUALIFIED,
            T_NAME_FULLY_QUALIFIED,
            T_NS_SEPARATOR,
            T_USE,
            T_AS,
            T_CLASS,
            T_FINAL,
            T_ABSTRACT,
            T_READONLY,
            T_EXTENDS,
            T_IMPLEMENTS,
            T_ATTRIBUTE,
        ];

        $extendsTestCase = false;
        $foundClass = false;

        for ($i = 0, $count = count(value: $tokens); $i < $count; $i++) {
            $token = $tokens[$i];

            // Skip simple characters (brackets, semicolons, etc.)
            if (is_string(value: $token)) {
                continue;
            }

            [$type, $value] = $token;

            // Once we hit a class definition, we've passed the global scope
            if ($type === T_CLASS) {
                $foundClass = true;
            }

            // Check for extends TestCase before the class body starts
            if ($type === T_EXTENDS && !$extendsTestCase) {
                for ($j = $i + 1; $j < $count && $j < $i + 5; $j++) {
                    if (is_array(value: $tokens[$j])) {
                        $nextValue = $tokens[$j][1];
                        if ($nextValue === 'TestCase' || str_ends_with(haystack: $nextValue, needle: '\\TestCase')) {
                            $extendsTestCase = true;
                            break;
                        }
                        if ($tokens[$j][0] !== T_WHITESPACE) {
                            break;
                        }
                    }
                }
            }

            // Before class definition, only allow safe tokens
            if (!$foundClass && !in_array(needle: $type, haystack: $safeGlobalTokens, strict: true)) {
                return false;
            }

            // Detect function calls in global scope: T_STRING followed by '('
            if (!$foundClass && $type === T_STRING) {
                // Look ahead for opening parenthesis (skip whitespace)
                for ($j = $i + 1; $j < $count; $j++) {
                    $nextToken = $tokens[$j];
                    if (is_array(value: $nextToken) && $nextToken[0] === T_WHITESPACE) {
                        continue;
                    }
                    // If next non-whitespace is '(', this is a function call
                    if ($nextToken === '(') {
                        return false;
                    }
                    break;
                }
            }

            // Once we've found the class and verified it extends TestCase, we're done checking
            if ($foundClass && $extendsTestCase) {
                return true;
            }
        }

        return $extendsTestCase;
    }

    /**
     * Find the fully qualified class name in a PHP file.
     */
    private function findClassInFile(string $file): ?string
    {
        $contents = file_get_contents(filename: $file);
        if ($contents === false) {
            return null;
        }

        $tokens = token_get_all(code: $contents);
        $namespace = '';

        for ($i = 0, $c = count(value: $tokens); $i < $c; $i++) {
            $token = $tokens[$i];

            // Skip string tokens (single characters like ; { } etc.)
            if (!is_array(value: $token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $parts = [];
                for ($j = $i + 1; isset($tokens[$j]) && $tokens[$j] !== ';'; $j++) {
                    $parts[] = is_array(value: $tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
                }
                $namespace = implode(separator: '', array: $parts);
            }

            if ($token[0] === T_CLASS) {
                // Look for class name token (skip whitespace)
                for ($j = $i + 1; $j < $c; $j++) {
                    if (!is_array(value: $tokens[$j])) {
                        continue;
                    }
                    if ($tokens[$j][0] === T_WHITESPACE) {
                        continue;
                    }
                    if ($tokens[$j][0] === T_STRING) {
                        $class = $tokens[$j][1];
                        return $namespace ? trim(string: $namespace) . '\\' . $class : $class;
                    }
                    break;
                }
            }
        }

        return null;
    }
}
