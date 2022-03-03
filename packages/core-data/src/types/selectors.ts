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
