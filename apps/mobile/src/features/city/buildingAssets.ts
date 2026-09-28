import type { ImageSourcePropType } from 'react-native';

/**
 * Available visuals for the buildings that currently have concrete
 * balance levels in packages/game-data/data/buildings.json.
 *
 * The map is explicit because Metro needs statically discoverable `require`
 * calls for local images. Unknown buildings intentionally fall back to the
 * category glyph used by the city slot.
 */
const BUILDING_ASSETS: Record<string, Record<number, ImageSourcePropType>> = {
  palace: {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    1: require('../../../assets/buildings/palace-level-1.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    2: require('../../../assets/buildings/palace-level-2.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    3: require('../../../assets/buildings/palace-level-3.png'),
  },
  farm: {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    1: require('../../../assets/buildings/farm-level-1.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    2: require('../../../assets/buildings/farm-level-2.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    3: require('../../../assets/buildings/farm-level-3.png'),
  },
  lumber_mill: {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    1: require('../../../assets/buildings/lumber-mill-level-1.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    2: require('../../../assets/buildings/lumber-mill-level-2.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    3: require('../../../assets/buildings/lumber-mill-level-3.png'),
  },
  quarry: {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    1: require('../../../assets/buildings/quarry-level-1.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    2: require('../../../assets/buildings/quarry-level-2.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    3: require('../../../assets/buildings/quarry-level-3.png'),
  },
  warehouse: {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    1: require('../../../assets/buildings/warehouse-level-1.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    2: require('../../../assets/buildings/warehouse-level-2.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    3: require('../../../assets/buildings/warehouse-level-3.png'),
  },
  barracks: {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    1: require('../../../assets/buildings/barracks-level-1.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    2: require('../../../assets/buildings/barracks-level-2.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    3: require('../../../assets/buildings/barracks-level-3.png'),
  },
  archery_range: {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    1: require('../../../assets/buildings/archery_range-level-1.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    2: require('../../../assets/buildings/archery_range-level-2.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    3: require('../../../assets/buildings/archery_range-level-3.png'),
  },
  stable: {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    1: require('../../../assets/buildings/stable-level-1.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    2: require('../../../assets/buildings/stable-level-2.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    3: require('../../../assets/buildings/stable-level-3.png'),
  },
  siege_workshop: {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    1: require('../../../assets/buildings/siege_workshop-level-1.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    2: require('../../../assets/buildings/siege_workshop-level-2.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    3: require('../../../assets/buildings/siege_workshop-level-3.png'),
  },
  academy: {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    1: require('../../../assets/buildings/academy-level-1.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    2: require('../../../assets/buildings/academy-level-2.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    3: require('../../../assets/buildings/academy-level-3.png'),
  },
  embassy: {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    1: require('../../../assets/buildings/embassy-level-1.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    2: require('../../../assets/buildings/embassy-level-2.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    3: require('../../../assets/buildings/embassy-level-3.png'),
  },
  marketplace: {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    1: require('../../../assets/buildings/marketplace-level-1.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    2: require('../../../assets/buildings/marketplace-level-2.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    3: require('../../../assets/buildings/marketplace-level-3.png'),
  },
  hospital: {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    1: require('../../../assets/buildings/hospital-level-1.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    2: require('../../../assets/buildings/hospital-level-2.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    3: require('../../../assets/buildings/hospital-level-3.png'),
  },
  walls: {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    1: require('../../../assets/buildings/walls-level-1.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    2: require('../../../assets/buildings/walls-level-2.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    3: require('../../../assets/buildings/walls-level-3.png'),
  },
  watchtower: {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    1: require('../../../assets/buildings/watchtower-level-1.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    2: require('../../../assets/buildings/watchtower-level-2.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    3: require('../../../assets/buildings/watchtower-level-3.png'),
  },
  iron_mine: {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    1: require('../../../assets/buildings/iron_mine-level-1.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    2: require('../../../assets/buildings/iron_mine-level-2.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    3: require('../../../assets/buildings/iron_mine-level-3.png'),
  },
  treasury: {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    1: require('../../../assets/buildings/treasury-level-1.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    2: require('../../../assets/buildings/treasury-level-2.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    3: require('../../../assets/buildings/treasury-level-3.png'),
  },
  tavern: {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    1: require('../../../assets/buildings/tavern-level-1.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    2: require('../../../assets/buildings/tavern-level-2.png'),
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    3: require('../../../assets/buildings/tavern-level-3.png'),
  },
};

export function getBuildingAsset(code: string, level: number): ImageSourcePropType | null {
  return BUILDING_ASSETS[code]?.[level] ?? null;
}
