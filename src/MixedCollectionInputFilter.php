<?php
/**
 * @author    Milan Divkovic <metalinspired@gmail.com>
 * @license   https://opensource.org/licenses/BSD-3-Clause New BSD License
 */

namespace metalinspired\MixedCollectionInputFilter;

use Laminas\InputFilter\BaseInputFilter;
use Laminas\InputFilter\Exception;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Validator\NotEmpty;

use function count;
use function get_debug_type;
use function is_iterable;
use function is_string;
use function max;
use function sprintf;

class MixedCollectionInputFilter extends InputFilter
{
    protected const MISSING_NAME_KEY = 'Missing name key for entry',
        MISSING_FILTER = 'Missing filter for entry';

    protected bool $isRequired = false;
    protected int $count = 0;
    protected array $collectionValues = [];
    protected array $collectionRawValues = [];
    protected array $collectionMessages = [];
    protected NotEmpty $notEmptyValidator;
    protected string $nameKey = '';
    /** @var BaseInputFilter[] */
    protected array $inputFilters = [];
    protected bool $nameKeyMissingInvalid = false;
    protected bool $filterMissingInvalid = false;

    public function __construct()
    {
        $this->notEmptyValidator = new NotEmpty();
    }

    public function setNameKey(string $name) : static
    {
        $this->nameKey = $name;

        return $this;
    }

    public function getNameKey() : string
    {
        return $this->nameKey;
    }

    /**
     * Set the input filter used for specific collection item type.
     *
     * @throws Exception\RuntimeException
     */
    public function setInputFilter(string $name, InputFilterInterface|iterable $inputFilter) : static
    {
        if (is_iterable($inputFilter)) {
            $inputFilter = $this->getFactory()->createInputFilter($inputFilter);
        }

        if (! $inputFilter instanceof BaseInputFilter) {
            throw new Exception\RuntimeException(sprintf(
                '%s expects an instance of %s; received "%s."',
                __METHOD__,
                BaseInputFilter::class,
                (get_debug_type($inputFilter))
            ));
        }

        $this->inputFilters[$name] = $inputFilter;

        return $this;
    }

    /**
     * Get the input filter used for specific collection item type.
     */
    public function getInputFilter(string $name): ?BaseInputFilter
    {
        return $this->inputFilters[$name] ?? null;
    }

    /**
     * Set input filters used for filtering collection items.
     *
     * @throws Exception\RuntimeException
     */
    public function setInputFilters(iterable $inputFilters) : static
    {
        foreach ($inputFilters as $name => $inputFilter) {
            if (! is_string($name)) {
                throw new Exception\RuntimeException('Input filter key is not a string.');
            }

            $this->setInputFilter($name, $inputFilter);
        }

        return $this;
    }

    /**
     * Get input filters used for filtering collection items.
     *
     * @return BaseInputFilter[]
     */
    public function getInputFilters() : array
    {
        return $this->inputFilters;
    }

    /**
     * Set behavior for when name key is missing in collection item.
     *
     * If set to true and collection item is missing a key with name
     * used for identifying input filter, validation will fail and
     * message of missing key will be added to messages array.
     */
    public function setNameKeyMissingInvalid(bool $invalid) : static
    {
        $this->nameKeyMissingInvalid = $invalid;

        return $this;
    }

    /**
     * Get behavior for when name key is missing in collection item.
     *
     * If set to true and collection item is missing a key with name
     * used for identifying input filter, validation will fail and
     * missing name key message will be added to messages array.
     *
     * @return bool
     */
    public function getNameKeyMissingInvalid() : bool
    {
        return $this->nameKeyMissingInvalid;
    }

    /**
     * Set behavior for when filter is missing for collection item.
     *
     * If set to true and value of name key in collection item
     * can't be mapped to input filter in input filters array,
     * validation will fail and missing filter message will be
     * added to messages array.
     */
    public function setFilterMissingInvalid(bool $invalid) : static
    {
        $this->filterMissingInvalid = $invalid;

        return $this;
    }

    /**
     * Get behavior for when filter is missing for collection item.
     *
     * If set to true and value of name key in collection item
     * can't be mapped to input filter in input filters array,
     * validation will fail and missing filter message will be
     * added to messages array.
     *
     * @return bool
     */
    public function getFilterMissingInvalid() : bool
    {
        return $this->filterMissingInvalid;
    }

    /**
     * Set if the collection can be empty.
     */
    public function setIsRequired(bool $isRequired) : static
    {
        $this->isRequired = $isRequired;

        return $this;
    }

    /**
     * Get if collection can be empty.
     */
    public function getIsRequired() : bool
    {
        return $this->isRequired;
    }

    /**
     * Set the count of data to validate.
     */
    public function setCount(int $count) : static
    {
        $this->count = max($count, 0);

        return $this;
    }

    /**
     * Get the count of data to validate, use the count of data by default.
     */
    public function getCount() : int
    {
        if (0 === $this->count) {
            return count($this->data);
        }

        return $this->count;
    }

    /**
     * {@inheritdoc}
     */
    public function setData($data): static
    {
        if (! is_iterable($data)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable collection; invalid collection of type %s provided.',
                __METHOD__,
                get_debug_type($data)
            ));
        }

        $this->setUnfilteredData($data);

        foreach ($data as $item) {
            if (is_iterable($item)) {
                continue;
            }

            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects each item in a collection to be an array or Traversable; '
                . 'invalid item in collection of type %s detected.',
                __METHOD__,
                get_debug_type($item)
            ));
        }

        $this->data = $data;

        return $this;
    }

    /**
     * Retrieve the NotEmpty validator to use for failed "required" validations.
     *
     * This validator will be used to produce a validation failure message in
     * cases where the collection is empty but required.
     */
    public function getNotEmptyValidator() : NotEmpty
    {
        return $this->notEmptyValidator;
    }

    /**
     * Set the NotEmpty validator to use for failed "required" validations.
     *
     * This validator will be used to produce a validation failure message in
     * cases where the collection is empty but required.
     */
    public function setNotEmptyValidator(NotEmpty $notEmptyValidator) : static
    {
        $this->notEmptyValidator = $notEmptyValidator;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @param mixed $context Ignored, but present to retain signature compatibility.
     */
    public function isValid($context = null) : bool
    {
        $this->collectionMessages = [];
        $valid = true;

        if ($this->isRequired && $this->getCount() < 1) {
            $this->collectionMessages[] = $this->prepareRequiredValidationFailureMessage();
            $valid = false;
        }

        if (count($this->data) < $this->getCount()) {
            $valid = false;
        }

        if (! $this->data) {
            $this->clearValues();
            $this->clearRawValues();

            return $valid;
        }

        foreach ($this->data as $key => $data) {
            if (! isset($data[$this->nameKey])) {
                if ($this->nameKeyMissingInvalid) {
                    $valid = false;
                    $this->collectionMessages[$key] = self::MISSING_NAME_KEY;
                }

                continue;
            }

            $inputFilter = $this->getInputFilter($data[$this->nameKey]);

            if (! $inputFilter) {
                if ($this->filterMissingInvalid) {
                    $valid = false;
                    $this->collectionMessages[$key] = self::MISSING_FILTER;
                }

                continue;
            }

            $inputFilter->setData($data);

            if (null !== $this->validationGroup) {
                $inputFilter->setValidationGroup($this->validationGroup[$key]);
            }

            if ($inputFilter->isValid()) {
                $this->validInputs[$key] = $inputFilter->getValidInput();
            } else {
                $valid = false;
                $this->collectionMessages[$key] = $inputFilter->getMessages();
                $this->invalidInputs[$key] = $inputFilter->getInvalidInput();
            }

            $this->collectionValues[$key] = $inputFilter->getValues();
            $this->collectionRawValues[$key] = $inputFilter->getRawValues();
        }

        return $valid;
    }

    /**
     * {@inheritdoc}
     */
    public function setValidationGroup($name) : static
    {
        if ($name === self::VALIDATE_ALL) {
            $this->validationGroup = null;
            return $this;
        }

        $this->validationGroup = $name;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getValues() : array
    {
        return $this->collectionValues;
    }

    /**
     * {@inheritdoc}
     */
    public function getRawValues() : array
    {
        return $this->collectionRawValues;
    }

    /**
     * Clear collectionValues
     *
     * @return array[]
     */
    public function clearValues() : array
    {
        return $this->collectionValues = [];
    }

    /**
     * Clear collectionRawValues
     *
     * @return array[]
     */
    public function clearRawValues() : array
    {
        return $this->collectionRawValues = [];
    }

    /**
     * {@inheritdoc}
     */
    public function getMessages() : array
    {
        return $this->collectionMessages;
    }

    /**
     * {@inheritdoc}
     */
    public function getUnknown() : array
    {
        if (! $this->data) {
            throw new Exception\RuntimeException(sprintf(
                '%s: no data present!',
                __METHOD__
            ));
        }

        $unknownInputs = [];

        foreach ($this->data as $key => $data) {
            if (! isset($data[$this->nameKey])) {
                $unknownInputs[$key] = self::MISSING_NAME_KEY;
                continue;
            }

            $inputFilter = $this->getInputFilter($data[$this->nameKey]);

            if (! $inputFilter) {
                $unknownInputs[$key] = self::MISSING_FILTER;
                continue;
            }

            $inputFilter->setData($data);

            $unknown = $inputFilter->getUnknown();

            if ($unknown) {
                $unknownInputs[$key] = $unknown;
            }
        }

        return $unknownInputs;
    }

    /**
     * @return array<string, string>
     */
    protected function prepareRequiredValidationFailureMessage() : array
    {
        $notEmptyValidator = $this->getNotEmptyValidator();
        $templates = $notEmptyValidator->getOption('messageTemplates');
        $message = $templates[NotEmpty::IS_EMPTY];
        $translator = $notEmptyValidator->getTranslator();

        return [
            NotEmpty::IS_EMPTY => $translator
                ? $translator->translate($message, $notEmptyValidator->getTranslatorTextDomain())
                : $message,
        ];
    }
}
