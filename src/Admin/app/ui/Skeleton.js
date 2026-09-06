/**
 * Skeleton placeholders. SH-05: same structure, no text.
 *
 */

/**
 * One grey block.
 *
 * @param {Object} props
 * @param {string} [props.className]
 */
export function Skel( { className = 'skel-line' } ) {
	return (
		<div
			className={ [ 'skel', className ].filter( Boolean ).join( ' ' ) }
		/>
	);
}

/**
 * Overview-shaped skeleton: hero + 3 stats + 2 cards.
 */
export default function Skeleton() {
	return (
		<div aria-busy="true" aria-live="polite">
			<article className="card hero-w is-open">
				<div className="hero-body hero-body-v6">
					<div className="hero-l">
						<Skel className="skel-title" />
						<Skel />
						<Skel />
						<Skel />
					</div>
					<div className="hero-svc">
						<Skel className="skel-title" />
						<Skel />
						<Skel />
						<Skel />
						<Skel />
					</div>
				</div>
			</article>
			<section className="sec">
				<div className="wpcy-grid-3">
					<article className="card stat-a">
						<Skel className="skel-title" />
						<Skel />
						<Skel className="skel-block" />
					</article>
					<article className="card stat-a">
						<Skel className="skel-title" />
						<Skel />
						<Skel className="skel-block" />
					</article>
					<article className="card stat-a">
						<Skel className="skel-title" />
						<Skel />
						<Skel className="skel-block" />
					</article>
				</div>
			</section>
			<section className="sec">
				<div className="grid2-w">
					<article className="card tight">
						<Skel className="skel-title" />
						<Skel />
						<Skel />
						<Skel />
					</article>
					<article className="card tight">
						<Skel className="skel-title" />
						<Skel />
						<Skel />
						<Skel />
					</article>
				</div>
			</section>
		</div>
	);
}
