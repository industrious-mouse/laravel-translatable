<?php

namespace Spatie\Translatable;

use Illuminate\Support\Str;
use Spatie\Translatable\Events\TranslationHasBeenSet;
use Spatie\Translatable\Exceptions\AttributeIsNotTranslatable;

trait HasTranslations
{
    /**
     * @param string $key
     *
     * @return mixed
     */
    public function getAttributeValue($key)
    {
        if (!$this->isTranslatableAttribute($key)) {
            return parent::getAttributeValue($key);
        }

        return $this->getTranslation($key, config('app.locale'));
    }

    /**
     * @param string $key
     * @param string $locale
     *
     * @return mixed
     */
    public function translate($key, $locale = '')
    {
        return $this->getTranslation($key, $locale);
    }

    /***
     * @param string $key
     * @param string $locale
     *
     * @return mixed
     */
    public function getTranslation($key, $locale)
    {
        $translations = $this->getTranslations($key);

        $translation = isset($translations[$locale]) ? $translations[$locale] : '';

        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $translation);
        }

        return $translation;
    }

    public function getTranslations($key)
    {
        $this->guardAgainstUntranslatableAttribute($key);

        $translation = isset($this->getAttributes()[$key]) ? $this->getAttributes()[$key] : '{}';

        return json_decode($translation, true);
    }

    /**
     * @param string $key
     * @param string $locale
     * @param $value
     *
     * @return $this
     */
    public function setTranslation($key, $locale, $value)
    {
        $this->guardAgainstUntranslatableAttribute($key);

        $translations = $this->getTranslations($key);

        $oldValue = isset($translations[$locale]) ? $translations[$locale] : '';

        if ($this->hasSetMutator($key)) {
            $method = 'set'.Str::studly($key).'Attribute';
            $value = $this->{$method}($value);
        }

        $translations[$locale] = $value;

        $this->attributes[$key] = $this->asJson($translations);

        event(new TranslationHasBeenSet($this, $key, $locale, $oldValue, $value));

        return $this;
    }

    /**
     * @param string $key
     * @param array  $translations
     *
     * @return $this
     */
    public function setTranslations($key, array $translations)
    {
        $this->guardAgainstUntranslatableAttribute($key);

        foreach ($translations as $locale => $translation) {
            $this->setTranslation($key, $locale, $translation);
        }

        return $this;
    }

    /**
     * @param string $key
     * @param string $locale
     *
     * @return $this
     */
    public function forgetTranslation($key, $locale)
    {
        $translations = $this->getTranslations($key);

        unset($translations[$locale]);

        $this->setAttribute($key, $translations);

        return $this;
    }

    public function getTranslatedLocales($key)
    {
        return array_keys($this->getTranslations($key));
    }

    protected function isTranslatableAttribute($key)
    {
        return in_array($key, $this->getTranslatableAttributes());
    }

    protected function guardAgainstUntranslatableAttribute($key)
    {
        if (!$this->isTranslatableAttribute($key)) {
            throw AttributeIsNotTranslatable::make($key, $this);
        }
    }

    public function getTranslatableAttributes()
    {
        return is_array($this->translatable)
            ? $this->translatable
            : [];
    }

    public function getCasts()
    {
        return array_merge(
            parent::getCasts(),
            array_fill_keys($this->getTranslatableAttributes(), 'array')
        );
    }
}
