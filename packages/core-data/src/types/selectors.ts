/**
 * Internal dependencies
 */
import type {
	Context,
	DefaultContextOf,
	EntityQuery,
	KeyOf,
	Kind,
	KindOf,
	Name,
	NameOf,
	RecordOf,
} from './index';
import { Updatable } from './index';

type State = any;

export type getEntityRecord = <
	R extends RecordOf< K, N >,
	C extends Context = DefaultContextOf< R >,
	K extends Kind = KindOf< R >,
	N extends Name = NameOf< R >,
	Q extends EntityQuery< any > = EntityQuery< C >
>(
	state: State,
	kind: K,
	name: N,
	key: KeyOf< R >,
	query?: Q
) =>
	| ( Q[ '_fields' ] extends string[]
			? Partial< RecordOf< K, N, C > >
			: RecordOf< K, N, C > )
	| null
	| undefined;

export type getEditedEntityRecord = <
	R extends RecordOf< K, N >,
	K extends Kind = KindOf< R >,
	N extends Name = NameOf< R >
>(
	state: State,
	kind: K,
	name: N,
	recordId: KeyOf< R >
) => Updatable< RecordOf< K, N, DefaultContextOf< R > > > | null | undefined;

export type getRawEntityRecord = getEditedEntityRecord;
