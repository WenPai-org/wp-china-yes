/**
 * RemixIcon line mapping. Semantic names match prototypes/e/build.py RI.
 *
 */

import {
	RiAppsLine,
	RiArrowDownSLine,
	RiArrowRightLine,
	RiArrowRightUpLine,
	RiBankCardLine,
	RiBookOpenLine,
	RiChat3Line,
	RiCheckLine,
	RiCloseLine,
	RiCloudLine,
	RiComputerLine,
	RiDashboard3Line,
	RiDownload2Line,
	RiEqualizerLine,
	RiErrorWarningLine,
	RiExternalLinkLine,
	RiEyeLine,
	RiFlashlightLine,
	RiFontSize,
	RiForbidLine,
	RiGlobalLine,
	RiHome4Line,
	RiImageLine,
	RiInformationLine,
	RiKey2Line,
	RiLeafLine,
	RiLifebuoyLine,
	RiLink,
	RiLoader4Line,
	RiMapPinLine,
	RiNotification3Line,
	RiPlugLine,
	RiPulseLine,
	RiQuestionLine,
	RiRefreshLine,
	RiRssLine,
	RiServerLine,
	RiShieldCheckLine,
	RiShoppingBag3Line,
	RiSparklingLine,
	RiStackLine,
	RiStethoscopeLine,
	RiStore2Line,
	RiTimeLine,
	RiUser3Line,
	RiVideoLine,
	RiWechatLine,
} from '@remixicon/react';

/**
 * Semantic name → RemixIcon file name (build.py RI, 47 entries).
 */
export const RI = {
	globe: 'global-line',
	bolt: 'flashlight-line',
	user: 'user-3-line',
	link: 'link',
	monitor: 'computer-line',
	check: 'check-line',
	info: 'information-line',
	arrow: 'arrow-right-line',
	map: 'map-pin-line',
	grid: 'apps-line',
	shield: 'shield-check-line',
	download: 'download-2-line',
	clock: 'time-line',
	block: 'forbid-line',
	gauge: 'dashboard-3-line',
	font: 'font-size',
	leaf: 'leaf-line',
	book: 'book-open-line',
	chat: 'chat-3-line',
	video: 'video-line',
	help: 'question-line',
	rss: 'rss-line',
	wechat: 'wechat-line',
	store: 'store-2-line',
	sparkle: 'sparkling-line',
	layers: 'stack-line',
	settings: 'equalizer-line',
	home: 'home-4-line',
	pulse: 'pulse-line',
	external: 'external-link-line',
	sliders: 'eye-line',
	image: 'image-line',
	chevron: 'arrow-down-s-line',
	up: 'arrow-right-up-line',
	spinner: 'loader-4-line',
	stethoscope: 'stethoscope-line',
	lifebuoy: 'lifebuoy-line',
	x: 'close-line',
	warn: 'error-warning-line',
	retry: 'refresh-line',
	plug: 'plug-line',
	server: 'server-line',
	cloud: 'cloud-line',
	bag: 'shopping-bag-3-line',
	key: 'key-2-line',
	card: 'bank-card-line',
	bell: 'notification-3-line',
};

const COMPONENTS = {
	globe: RiGlobalLine,
	bolt: RiFlashlightLine,
	user: RiUser3Line,
	link: RiLink,
	monitor: RiComputerLine,
	check: RiCheckLine,
	info: RiInformationLine,
	arrow: RiArrowRightLine,
	map: RiMapPinLine,
	grid: RiAppsLine,
	shield: RiShieldCheckLine,
	download: RiDownload2Line,
	clock: RiTimeLine,
	block: RiForbidLine,
	gauge: RiDashboard3Line,
	font: RiFontSize,
	leaf: RiLeafLine,
	book: RiBookOpenLine,
	chat: RiChat3Line,
	video: RiVideoLine,
	help: RiQuestionLine,
	rss: RiRssLine,
	wechat: RiWechatLine,
	store: RiStore2Line,
	sparkle: RiSparklingLine,
	layers: RiStackLine,
	settings: RiEqualizerLine,
	home: RiHome4Line,
	pulse: RiPulseLine,
	external: RiExternalLinkLine,
	sliders: RiEyeLine,
	image: RiImageLine,
	chevron: RiArrowDownSLine,
	up: RiArrowRightUpLine,
	spinner: RiLoader4Line,
	stethoscope: RiStethoscopeLine,
	lifebuoy: RiLifebuoyLine,
	x: RiCloseLine,
	warn: RiErrorWarningLine,
	retry: RiRefreshLine,
	plug: RiPlugLine,
	server: RiServerLine,
	cloud: RiCloudLine,
	bag: RiShoppingBag3Line,
	key: RiKey2Line,
	card: RiBankCardLine,
	bell: RiNotification3Line,
};

/**
 * RemixIcon by semantic name. color currentColor; class ph matches prototype CSS.
 *
 * @param {Object} props
 * @param {string} props.name      Semantic name from RI.
 * @param {number} [props.size=18]
 */
export default function Icon( { name, size = 18 } ) {
	const Cmp = COMPONENTS[ name ];
	if ( ! Cmp ) {
		return null;
	}
	return (
		<Cmp
			size={ size }
			color="currentColor"
			className="ph"
			aria-hidden="true"
		/>
	);
}
