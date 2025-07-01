<?php
/**
 * ForceSkin extension - force a skin on all users of the wiki, regardless of their
 * user preferences.
 *
 * Based on an idea and the "SkinForce" extension written by David Pean for ArmchairGM.
 * Rewritten from scratch by Jack Phoenix on 17 October 2023.
 *
 * I don't think this extension is very useful outside wiki farms, but perhaps I'm wrong.
 *
 * The PHP code here works with MediaWiki 1.19 and newer, since that's the version which
 * introduced the "RequestContextCreateSkin" hook. MediaWiki 1.25 introduced the concept
 * of "extension registration" (extension.json), and manifest_version 2 was introduced
 * in MW 1.29+, hence the minimum version requirement (for the time being).
 * If you'd rewrite the extension.json back to pure PHP, you could have this compatible
 * with MW 1.19-1.28.
 * (Actually scratch that, the SkinFactory service used in the GetPreferences hook
 * requires MW 1.27+ IIRC; but you *could* replace that for older MWs or use method_exists()
 * to switch between the services version vs. the ancient method call...)
 *
 * All that said, I tested this (only) on a MediaWiki 1.39 box. Any support for any and
 * all other versions, especially older ones, is purely coincidental at best.
 *
 * @file
 * @date 17 October 2023
 * @author Jack Phoenix
 */
class ForceSkin {

	/**
	 * Force a skin, either from the ForceSkin cookie (for testing/development only) or
	 * from the $wgForceSkin configuration variable.
	 * The latter, if set, overrides the former.
	 * The ForceSkin cookie gets set here for cache varying purposes.
	 *
	 * @param IContextSource $context
	 * @param Skin|string|null &$skin Initially null, will be set to a string by us, which core code
	 *   will use to build a Skin instance
	 */
	public static function onRequestContextCreateSkin( $context, &$skin ) {
		global $wgForceSkin;

		$request = $context->getRequest();
		$cookie = $request->getCookie( 'ForceSkin' );
		$hasUseskin = ( $request->getRawVal( 'useskin' ) !== '' );

		if ( $cookie && !$hasUseskin ) {
			// Cookie is set but no ?useskin= in URL -> fetch skin name from cookie
			$skin = $cookie;
		} elseif ( $hasUseskin ) {
			// If the useskin param IS set, allow using it to override the currently "forced" skin ;)
			$skin = Skin::normalizeKey( $request->getRawVal( 'useskin' ) );
			// Set the cookie so that the user choice "sticks"
			$request->response()->setCookie( 'ForceSkin', $skin );
		}

		// Actual forced skin from site config takes precedence over any and all other values, though
		if ( $wgForceSkin !== '' ) {
			$skin = $wgForceSkin;
		}
	}

	/**
	 * If a skin is forced, remove all others from the Special:Preferences view
	 * and say that the forced skin is the default skin, even if $wgDefaultSkin != $wgForceSkin.
	 *
	 * @param User $user
	 * @param array &$preferences
	 */
	public static function onGetPreferences( $user, array &$preferences ) {
		global $wgForceSkin;

		if ( $wgForceSkin ) {
			$factory = MediaWiki\MediaWikiServices::getInstance()->getSkinFactory();
			$normalized = Skin::normalizeKey( $wgForceSkin );
			$skin = $factory->makeSkin( $normalized );

			$options[$skin->getSkinName() . ' ' . wfMessage( 'parentheses' )
							->rawParams( wfMessage( 'default' )->escaped() )
							->escaped()] = strtolower( $wgForceSkin );

			$preferences['skin'] = [
				'type' => 'radio',
				'options' => $options,
				'section' => 'rendering/skin',
				'default' => strtolower( $wgForceSkin )
			];
		}
	}

	/**
	 * Vary cache on the ForceSkin cookie.
	 *
	 * @param OutputPage $out
	 * @param array &$cookies
	 */
	public static function onGetCacheVaryCookies( $out, &$cookies ) {
		$cookie = $out->getRequest()->getCookie( 'ForceSkin' );
		if ( $cookie ) {
			$cookies[] = 'ForceSkin';
		}
	}
}
