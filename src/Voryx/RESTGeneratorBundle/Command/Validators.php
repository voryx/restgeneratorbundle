<?php


namespace Voryx\RESTGeneratorBundle\Command;

/**
 * Validator functions.
 *
 * @author Maarten Sprakel <maarten.sprakel@extendas.com>
 */
class Validators
{
    /**
     * @param string $format
     * @return string
     * @throws \RuntimeException
     */
    public static function validateTestFormat($format)
    {
        if (!$format) {
            return 'none';
        }

        if ($format === 'oauth') {
            $format = 'oauth2';
        }

        $format = strtolower($format);

        $supported = array('none', 'oauth2', 'no-authentication', 'csrf');

        if (!in_array($format, $supported)) {
            throw new \RuntimeException(sprintf('Test format "%s" is not supported, only '.implode(',',$supported).' are supported.', $format));
        }

        return $format;
    }

    /**
     * @param string $service_format
     * @return string
     * @throws \RuntimeException
     */
    public static function validateServiceFormat($service_format)
    {
        if (!$service_format)
        {
            return 'xml';
        }

        $service_format = strtolower($service_format);

        $supported_service_formats = array('xml', 'yml');

        if (!in_array($service_format, $supported_service_formats))
        {
            throw new \RuntimeException(sprintf('Service format "%s" is not supported, only '.implode(',',$supported_service_formats).' are supported.', $service_format));
        }

        return $service_format;
    }
}
