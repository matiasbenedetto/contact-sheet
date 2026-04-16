import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { PanelBody, RangeControl, SelectControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const ASPECT_RATIO_OPTIONS = [
	{ label: __( 'Original', 'contact-sheet' ), value: 'original' },
	{ label: __( 'Square (1:1)', 'contact-sheet' ), value: 'square' },
	{ label: '4:3', value: '4-3' },
	{ label: '3:2', value: '3-2' },
	{ label: '16:9', value: '16-9' },
	{ label: __( '3:4 (Portrait)', 'contact-sheet' ), value: '3-4' },
	{ label: __( '2:3 (Portrait)', 'contact-sheet' ), value: '2-3' },
];

const SIZE_CONSTRAINT_OPTIONS = [
	{ label: __( 'None', 'contact-sheet' ), value: 'none' },
	{ label: __( 'Fixed Height', 'contact-sheet' ), value: 'height' },
	{ label: __( 'Fixed Width', 'contact-sheet' ), value: 'width' },
];

function extractImages( content ) {
	const parser = new DOMParser();
	const doc = parser.parseFromString( content, 'text/html' );
	return Array.from( doc.querySelectorAll( 'img' ) ).map( ( img ) => ( {
		src: img.getAttribute( 'src' ) || '',
		alt: img.getAttribute( 'alt' ) || '',
	} ) );
}

export default function Edit( { attributes, setAttributes, context } ) {
	const blockProps = useBlockProps();
	const { aspectRatio, sizeConstraint, sizeValue, borderRadius } = attributes;
	const { postId, postType } = context;

	const post = useSelect(
		( select ) => {
			if ( ! postId ) return null;
			return select( 'core' ).getEntityRecord( 'postType', postType || 'post', postId );
		},
		[ postId, postType ]
	);

	if ( ! postId ) {
		return (
			<div { ...blockProps }>
				<p>{ __( 'Photo Strip: place this block inside a Query Loop.', 'contact-sheet' ) }</p>
			</div>
		);
	}

	if ( ! post ) {
		return (
			<div { ...blockProps }>
				<Spinner />
			</div>
		);
	}

	const images = extractImages( post.content?.rendered || '' );

	const imgStyle = { borderRadius: borderRadius + 'px' };

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Image Settings', 'contact-sheet' ) }>
					<SelectControl
						label={ __( 'Aspect Ratio', 'contact-sheet' ) }
						value={ aspectRatio }
						options={ ASPECT_RATIO_OPTIONS }
						onChange={ ( value ) => setAttributes( { aspectRatio: value } ) }
					/>
					<SelectControl
						label={ __( 'Size Constraint', 'contact-sheet' ) }
						value={ sizeConstraint }
						options={ SIZE_CONSTRAINT_OPTIONS }
						help={ __( 'Fix one dimension while the other scales freely.', 'contact-sheet' ) }
						onChange={ ( value ) => setAttributes( { sizeConstraint: value } ) }
					/>
					{ sizeConstraint !== 'none' && (
						<RangeControl
							label={ sizeConstraint === 'height' ? __( 'Height (px)', 'contact-sheet' ) : __( 'Width (px)', 'contact-sheet' ) }
							value={ sizeValue }
							onChange={ ( value ) => setAttributes( { sizeValue: value } ) }
							min={ 50 }
							max={ 500 }
							step={ 10 }
						/>
					) }
					<RangeControl
						label={ __( 'Border Radius (px)', 'contact-sheet' ) }
						value={ borderRadius }
						onChange={ ( value ) => setAttributes( { borderRadius: value } ) }
						min={ 0 }
						max={ 50 }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<div className="photo-strip-item">
					{ images.length > 0 ? (
						<div className="photo-strip-images" style={{ '--ps-height': sizeValue + 'px' }}>
							{ images.map( ( image, index ) => (
								<div
									key={ index }
									className={ `photo-strip-image photo-strip-aspect-${ aspectRatio } photo-strip-constraint-${ sizeConstraint }` }
								>
									<img src={ image.src } alt={ image.alt } loading="lazy" style={ imgStyle } />
								</div>
							) ) }
						</div>
					) : (
						<p>{ __( 'No images found in this post.', 'contact-sheet' ) }</p>
					) }
				</div>
			</div>
		</>
	);
}
