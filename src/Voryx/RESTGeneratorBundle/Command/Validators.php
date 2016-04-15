<?php


namespace Voryx\RESTGeneratorBundle\Command;

/**
 * Validator functions.
 *
 * @author Maarten Sprakel <maarten.sprakel@extendas.com>
 */
class Validators
{
    public static function validateTestFormat($format)
    {
        if (!$format) {
            return 'none';
        }

        if ($format === 'oauth') {
            $format = 'oauth2';
        }

        $format = strtolower($format);

        if (!in_array($format, array('none', 'oauth2', 'no-authentication', 'csrf'))) {
            throw new \RuntimeException(sprintf('Test format "%s" is not supported, only '.implode(',',$format).' are supported.', $format));
        }

        return $format;
    }
}
