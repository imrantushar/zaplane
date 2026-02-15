import React, { useContext, useState } from 'react';
import { useSelector } from 'react-redux';

import './styles.scss';
import { plugin_root_url } from '@ZAPUtils/helper';

const CustomTableMessage = ({ title, subText, button }) => {
	const [isImageLoaded, setIsImageLoaded] = useState(false);
	

	const handleImageLoad = () => {
		setIsImageLoaded(true);
	};


	const imageSrc = plugin_root_url + 'assets/images/noDataAvailable.svg';

	return (
		<div className={`zaplane-oops zaplane-oops__message`}>
			<div className="zaplane-oops__icon">
				<img src={imageSrc} alt="" onLoad={handleImageLoad} />
			</div>

			{isImageLoaded && (
				<div className="zaplane-oops__content">
					<h3 className="zaplane-oops__heading">{title}</h3>
					<h3 className="zaplane-oops__text">{subText}</h3>
					<div className="zaplane-oops__button">{button}</div>
				</div>
			)}
		</div>
	);
};

export default CustomTableMessage;
