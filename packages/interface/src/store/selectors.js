/**
 * WordPress dependencies
 */
import { createRegistrySelector } from '@wordpress/data';
import deprecated from '@wordpress/deprecated';
import { store as preferencesStore } from '@wordpress/preferences';

/**
 * Returns the complementary area that is active in a given scope.
 *
 * @param {Object} state Global application state.
 * @param {string} scope Item scope.
 *
 * @return {string} The complementary area that is active in the given scope.
 */
export const getActiveComplementaryArea = createRegistrySelector(
	( select ) => ( state, scope ) => {
		return select( preferencesStore ).get( scope, 'complementaryArea' );
	}
);

/**
 * Returns a boolean indicating if an item is pinned or not.
 *
 * @param {Object} state Global application state.
 * @param {string} scope Scope.
 * @param {string} item  Item to check.
 *
 * @return {boolean} True if the item is pinned and false otherwise.
 */
export const isItemPinned = createRegistrySelector(
	( select ) => ( state, scope, item ) => {
		const pinnedItems = select( preferencesStore ).get(
			scope,
			'pinnedItems'
		);
		return pinnedItems?.[ item ] ?? true;
	}
);

/**
 * Returns a boolean indicating whether a feature is active for a particular
 * scope.
 *
 * @param {Object} state       The store state.
 * @param {string} scope       The scope of the feature (e.g. core/edit-post).
 * @param {string} featureName The name of the feature.
 *
 * @return {boolean} Is the feature enabled?
 */
export const isFeatureActive = createRegistrySelector(
	( select ) => ( state, scope, featureName ) => {
		deprecated(
			`wp.select( 'core/interface' ).isFeatureActive( scope, featureName )`,
			{
				since: '6.0',
				alternative: `!! wp.select( 'core/preferences' ).isFeatureActive( scope, featureName )`,
			}
		);

		return !! select( preferencesStore ).get( scope, featureName );
	}
);
