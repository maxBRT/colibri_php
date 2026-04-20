import clsx from 'clsx';
import Heading from '@theme/Heading';
import styles from './styles.module.css';

const FeatureList = [
  {
    title: 'Easy to Use',
    description: (
      <>
        Colibri is design to make dealing with RSS feed as straight foward as possible.
      </>
    ),
  },
  {
    title: 'Focus on What Matters',
    description: (
      <>
        Colibri prioritizes a consistent, intuitive user experience. While more streamlined than some RSS feed, its clean interface provides everything you need to build applications.
      </>
    ),
  },
  {
    title: 'Developer Friendly', // Option A: Focusing on the Tech/API
    description: (
      <>
        Built with a modern backend and a clean API, Colibri is easy to 
        integrate, extend, or self-host for your own personal news feed.
      </>
    ),
  },
];

function Feature({title, description}) {
  return (
    <div className={clsx('col col--4')}>
      <div className="text--center padding-horiz--md">
        <Heading as="h3">{title}</Heading>
        <p>{description}</p>
      </div>
    </div>
  );
}

export default function HomepageFeatures() {
  return (
    <section className={styles.features}>
      <div className="container">
        <div className="row">
          {FeatureList.map((props, idx) => (
            <Feature key={idx} {...props} />
          ))}
        </div>
      </div>
    </section>
  );
}
