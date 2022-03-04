/**
 * Internal dependencies
 */
import { Context } from './helpers';
import { CoreEntity } from '../entities';

/**
 * The type that the entries of PerPackageEntities must adhere to. This is for reference only,
 * there is no type checking in place.
 *
 * An entity is like a data type. Core-data knows how to handle data requests
 * similar to `getEntityRecord( "root", "comment", 15 )` thanks to the entity configuration
 * that ties the entity kind (a namespace) and name ("root" and "comment") to information
 * such as the REST API endpoint URL and the primary key field.
 *
 * Core-data TypeScript types also associate the same kinds and names to their related
 * Record data type so that calling `getEntityRecord( "root", "comment", 15 )` returns
 * a list of a Comment objects.
 */
export interface EntityInterface< C extends Context > {
	/**
	 * The namespace of the current Entity.
	 */
	kind: string;
	/**
	 * The name of the current Entity, unique within the `kind` namespace.
	 */
	name: string;
	/**
	 * The type of the records of the current Entity. It can be optionally parametrized
	 * by Context.
	 */
	recordType: Object;
	/**
	 * The name of the primary key field of the current Entity.
	 */
	key: string;
	/**
	 * The default value of the `context` query parameter that the related API
	 * endpoint assumes when no context is given.
	 */
	defaultContext: Context;
}

/**
 * An interface that may be extended to add types for new entities. Each entry
 * must be a union of entity definitions adhering to the EntityInterface type.
 *
 * Example:
 *
 * ```ts
 * import type { Context } from '@wordpress/core-data';
 * // ...
 *
 * interface Order {
 *   id: number;
 *   clientId: number;
 *   // ...
 * }
 *
 * type OrderEntity = {
 *   kind: 'myPlugin';
 *   name: 'order';
 *   recordType: Order;
 * }
 *
 * declare module '@wordpress/core-data' {
 *     export interface PerPackageEntities< C extends Context > {
 *         myPlugin: OrderEntity | ClientEntity
 *     }
 * }
 *
 * const c = getEntityRecord( 'myPlugin', 'order', 15 );
 * // c is of the type Order
 * ```
 */
export interface PerPackageEntities< C extends Context > {
	core: CoreEntity< C >;
}

/**
 * A union of all the registered entities.
 */
type Entity<
	C extends Context = any
> = PerPackageEntities< C >[ keyof PerPackageEntities< C > ];

/**
 * A union of all known record types.
 */
export type EntityRecord<
	C extends Context = any
> = Entity< C >[ 'recordType' ];

/**
 * An entity corresponding to a specified record type.
 */
type EntityOf< R extends EntityRecord > = Extract< Entity, { recordType: R } >;

/**
 * Name of the requested entity.
 */
export type NameOf< R extends EntityRecord > = EntityOf< R >[ 'name' ];

/**
 * Kind of the requested entity.
 */
export type KindOf< R extends EntityRecord > = EntityOf< R >[ 'kind' ];

/**
 * Primary key type of the requested entity, sourced from PerPackageEntities.
 *
 * For core entities, the key type is computed using the entity configuration in entities.js.
 */
export type KeyOf<
	R extends EntityRecord
> = EntityOf< R >[ 'key' ] extends keyof R
	? R[ EntityOf< R >[ 'key' ] ]
	: never;

/**
 * Default context of the requested entity, sourced from PerPackageEntities.
 *
 * For core entities, the default context is extracted from the entity configuration
 * in entities.js.
 */
export type DefaultContextOf<
	R extends EntityRecord
> = EntityOf< R >[ 'defaultContext' ];

/**
 * An entity record type associated with specified kind and name, sourced from PerPackageEntities.
 */
export type RecordOf<
	K extends Kind,
	N extends Name,
	C extends Context = any
> = Extract< Entity< C >, { kind: K; name: N } >[ 'recordType' ];

/**
 * A union of all known entity kinds.
 */
export type Kind = Entity[ 'kind' ];
/**
 * A union of all known entity names.
 */
export type Name = Entity[ 'name' ];
